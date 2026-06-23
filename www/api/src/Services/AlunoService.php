<?php

declare(strict_types=1);

namespace FMP\RMApi\Services;

use FMP\RMApi\Clients\RMSoapClient;
use FMP\RMApi\Exceptions\FluxoException;
use FMP\RMApi\Exceptions\RMException;
use FMP\RMApi\Helpers\Crypto;
use FMP\RMApi\Helpers\Validation;
use Throwable;

/**
 * Operações de Aluno no RM:
 *  - EduAlunoData          (cadastro do aluno)
 *  - EduUsuarioFilialData  (vínculo usuário x filial)
 *  - GlbUsuarioData        (status/senha do usuário p/ SSO)
 */
class AlunoService
{
    public const DATASERVER_ALUNO          = 'EduAlunoData';
    public const DATASERVER_USUARIO_FILIAL = 'EduUsuarioFilialData';
    public const DATASERVER_USUARIO        = 'GlbUsuarioData';

    public function __construct(
        private readonly RMSoapClient $rm,
        private readonly ConsultaService $consulta,
        private readonly array $rmConfig,
        private readonly Crypto $crypto
    ) {
    }

    private function contexto(string|int $codColigada, string|int $codTipoCurso, string|int $codFilial): array
    {
        return [
            'CODCOLIGADA'  => $codColigada,
            'CODTIPOCURSO' => $codTipoCurso,
            'CODFILIAL'    => $codFilial,
            'CODSISTEMA'   => $this->rmConfig['contexto_padrao']['CODSISTEMA'] ?? 'S',
            'CODUSUARIO'   => $this->rmConfig['usuario_servico'] ?? 'integra.eduvem',
        ];
    }

    /**
     * Dados do aluno (RA, CODUSUARIO, SENHAPADRAO, EXISTESUSUARIOFILIAL...).
     */
    public function buscar(string|int $codPessoa, string|int $codColigada): ?array
    {
        return $this->consulta->aluno($codPessoa, $codColigada);
    }

    /* =====================================================================
     * Fluxo orquestrado com rastreamento de etapas (igual à inscrição)
     * ===================================================================== */

    /**
     * Cria/atualiza o aluno rodando todas as etapas e devolvendo o rastro:
     *   CLIENTE/FORNECEDOR → ALUNO → USUÁRIO/FILIAL → ACESSO (SSO).
     *
     * Em caso de falha de RM, lança FluxoException já com as etapas concluídas
     * (o error handler central devolve 422 + etapa + etapas_concluidas).
     *
     * @return array{chave:string, autoLogin:bool, nextUrl:string, etapas:array, aluno:array}
     */
    public function criarFluxo(array $in, string $baseUrl): array
    {
        $etapas = [];
        $add = function (string $etapa, string $detalhe, string $status = 'OK') use (&$etapas): void {
            $etapas[] = ['etapa' => $etapa, 'status' => $status, 'detalhe' => $detalhe];
        };

        /* ---------- Validação ---------- */
        $codPessoa    = Validation::ensureHasValue($in, 'CODPESSOA');
        $codColigada  = Validation::ensureHasValue($in, 'CODCOLIGADA');
        $codTipoCurso = Validation::ensureHasValue($in, 'CODTIPOCURSO');
        $codFilial    = Validation::ensureHasValue($in, 'CODFILIAL');

        $cpf = isset($in['CPF']) && $in['CPF'] !== '' ? preg_replace('/\D/', '', (string) $in['CPF']) : '';
        $rnm = (string) ($in['RNM'] ?? '');

        $add('VALIDAÇÃO', 'Dados de entrada validados');

        /* ---------- Cliente/Fornecedor (validação via consulta de CFO) ---------- */
        $cliFor = ($cpf !== '' || $rnm !== '') ? $this->consulta->cliForPorCpfRnm($cpf, $rnm) : null;
        if ($cliFor !== null) {
            $add(
                'CLIENTE/FORNECEDOR',
                "Cliente/Fornecedor localizado (CODCFO {$cliFor['CODCFO']}); será vinculado ao aluno",
                'ENCONTRADO'
            );
        } else {
            $add(
                'CLIENTE/FORNECEDOR',
                'Nenhum cliente/fornecedor encontrado para o documento; aluno será criado sem vínculo',
                'NAO_ENCONTRADO'
            );
        }

        /* ---------- Aluno (EduAlunoData) ---------- */
        $alunoJaExistia = $this->consulta->aluno($codPessoa, $codColigada) !== null;
        try {
            $chave = $this->salvar($codPessoa, $codColigada, $codTipoCurso, $codFilial, $cpf, $rnm);
        } catch (RMException $e) {
            throw $this->falha('ALUNO', 'Houve um erro ao gravar o aluno.', $etapas, $e);
        }
        $add('ALUNO', ($alunoJaExistia ? 'Aluno atualizado' : 'Aluno criado') . ". Chave: {$chave}", $alunoJaExistia ? 'ATUALIZADO' : 'OK');

        $student = $this->buscar($codPessoa, $codColigada);
        if ($student === null || empty($student['CODUSUARIO'])) {
            throw $this->falhaMsg('ALUNO', 'Não foi possível obter o usuário do aluno gravado.', $etapas, $student);
        }

        /* ---------- Usuário/Filial (EduUsuarioFilialData) ---------- */
        if (($student['EXISTESUSUARIOFILIAL'] ?? '') === 'N') {
            try {
                $this->garantirUsuarioFilial($student['CODUSUARIO'], $codColigada, $codTipoCurso, $codFilial);
            } catch (RMException $e) {
                throw $this->falha('USUÁRIO/FILIAL DO ALUNO', 'Erro ao definir o usuário/filial do aluno.', $etapas, $e);
            }
            $add('USUÁRIO/FILIAL DO ALUNO', "Vínculo usuário/filial criado para {$student['CODUSUARIO']}");
        } else {
            $add('USUÁRIO/FILIAL DO ALUNO', "Usuário/filial já existia para {$student['CODUSUARIO']}", 'JA_EXISTIA');
        }

        /* ---------- Acesso / SSO (GlbUsuarioData) ---------- */
        $temSenhaPadrao = $this->temSenhaPadrao($student['CODUSUARIO'], (string) ($student['SENHAPADRAO'] ?? ''));
        $nuncaAcessou   = empty($student['DATAULTIMOACESSOVALIDO']);

        if ($nuncaAcessou || $temSenhaPadrao) {
            try {
                $this->ajustarAcessoUsuario($student['CODUSUARIO'], (string) $student['SENHAPADRAO']);
            } catch (RMException $e) {
                throw $this->falha('ACESSO', 'Erro ao ajustar o acesso do usuário.', $etapas, $e);
            }

            $token     = $this->crypto->encrypt($student['CODUSUARIO'] . '$_$' . $student['SENHAPADRAO']);
            $autoLogin = true;
            $nextUrl   = sprintf('%s/sso/%s', $baseUrl, $token);
            $add('ACESSO', "SSO de primeiro acesso gerado para {$student['CODUSUARIO']}");
        } else {
            $autoLogin = false;
            $nextUrl   = $this->rmConfig['portal']['login_url'] ?? '';
            $add('ACESSO', "Aluno {$student['CODUSUARIO']} já alterou a senha. Login manual no portal");
        }

        return [
            'chave'      => $chave,
            'RA'         => $student['RA'] ?? null,
            'CODUSUARIO' => $student['CODUSUARIO'],
            'autoLogin'  => $autoLogin,
            'nextUrl'    => $nextUrl,
            'etapas'     => $etapas,
        ];
    }

    /**
     * Vincula um Cliente/Fornecedor já existente a um aluno já gravado (por RA).
     * Lê o aluno pela PK (CODCOLIGADA;RA) para preservar CODPESSOA/CODTIPOCURSO
     * e regrava o EduAlunoData com o CFO anexado.
     *
     * Body esperado: RA, CODCOLIGADA, CODTIPOCURSO, CODFILIAL e
     *   (CODCFO [+ CODCOLCFO])  OU  (CPF/CGCCFO para localizar o CFO).
     *
     * @return array{chave:string, etapas:array}
     */
    public function vincularCliente(array $in): array
    {
        $etapas = [];
        $add = function (string $etapa, string $detalhe, string $status = 'OK') use (&$etapas): void {
            $etapas[] = ['etapa' => $etapa, 'status' => $status, 'detalhe' => $detalhe];
        };

        $ra           = (string) Validation::ensureHasValue($in, 'RA');
        $codColigada  = (string) Validation::ensureHasValue($in, 'CODCOLIGADA');
        $codTipoCurso = Validation::ensureHasValue($in, 'CODTIPOCURSO');
        $codFilial    = Validation::ensureHasValue($in, 'CODFILIAL');
        $add('VALIDAÇÃO', 'Dados de entrada validados');

        /* ---------- Localiza o aluno pela PK ---------- */
        try {
            $record = $this->rm->readRecord(
                self::DATASERVER_ALUNO,
                [$codColigada, $ra],
                $this->contexto($codColigada, $codTipoCurso, $codFilial)
            );
        } catch (RMException $e) {
            throw $this->falha('ALUNO', 'Erro ao localizar o aluno.', $etapas, $e);
        }

        $saluno = $record['SAluno'] ?? ($record['EduAluno']['SAluno'] ?? null);
        $codPessoa = is_array($saluno) ? ($saluno['CODPESSOA'] ?? null) : null;

        if ($codPessoa === null || $codPessoa === '') {
            throw $this->falhaMsg(
                'ALUNO',
                "Aluno (RA {$ra}, coligada {$codColigada}) não encontrado. Crie o aluno antes de vincular o cliente.",
                $etapas,
                $record
            );
        }
        // Preserva o tipo de curso do registro quando disponível.
        $codTipoCursoReg = is_array($saluno) ? ($saluno['CODTIPOCURSO'] ?? $codTipoCurso) : $codTipoCurso;
        $add('ALUNO', "Aluno RA {$ra} localizado (CODPESSOA {$codPessoa})", 'ENCONTRADO');

        /* ---------- Resolve / valida o CFO ---------- */
        if (!empty($in['CODCFO'])) {
            $codCfo    = (string) $in['CODCFO'];
            $codColCfo = (string) ($in['CODCOLCFO'] ?? $codColigada);
        } else {
            $doc = preg_replace('/\D/', '', (string) ($in['CPF'] ?? $in['CGCCFO'] ?? $in['DOC'] ?? ''));
            $cliFor = $doc !== '' ? $this->consulta->cliForPorCpfRnm($doc, (string) ($in['RNM'] ?? '')) : null;
            if ($cliFor === null) {
                throw $this->falhaMsg(
                    'CLIENTE/FORNECEDOR',
                    'Cliente/Fornecedor não encontrado. Informe CODCFO ou um documento válido (grave o cliente primeiro).',
                    $etapas,
                    $in
                );
            }
            $codCfo    = (string) $cliFor['CODCFO'];
            $codColCfo = (string) $cliFor['CODCOLCFO'];
        }
        $add('CLIENTE/FORNECEDOR', "Cliente/Fornecedor {$codColCfo};{$codCfo} validado", 'ENCONTRADO');

        /* ---------- Regrava o aluno com o CFO anexado ---------- */
        $xml = <<<XML
        <EduAluno>
            <SAluno>
                <CODCOLIGADA>{$codColigada}</CODCOLIGADA>
                <RA>{$ra}</RA>
                <CODCOLCFO>{$codColCfo}</CODCOLCFO>
                <CODCFO>{$codCfo}</CODCFO>
                <CODPESSOA>{$codPessoa}</CODPESSOA>
                <CODTIPOCURSO>{$codTipoCursoReg}</CODTIPOCURSO>
            </SAluno>
            <SAlunoCompl>
                <CODCOLIGADA>{$codColigada}</CODCOLIGADA>
                <RA>{$ra}</RA>
            </SAlunoCompl>
        </EduAluno>
        XML;

        $contexto = $this->contexto($codColigada, $codTipoCursoReg, $codFilial);
        try {
            $result = $this->rm->saveRecord(self::DATASERVER_ALUNO, $xml, $contexto);
        } catch (RMException $e) {
            throw $this->falha('VÍNCULO', 'Erro ao vincular o cliente/fornecedor ao aluno.', $etapas, $e);
        }

        $add('VÍNCULO', "Cliente/Fornecedor {$codCfo} vinculado ao aluno RA {$ra}");

        return ['chave' => $result, 'etapas' => $etapas];
    }

    private function falha(string $etapa, string $feedback, array $etapas, RMException $e): FluxoException
    {
        $f = new FluxoException($etapa, $feedback, "Erro em {$etapa}: {$e->retornoRm}", $e->xmlEnviado, $e);
        $f->etapasConcluidas = $etapas;
        return $f;
    }

    private function falhaMsg(string $etapa, string $feedback, array $etapas, mixed $payload): FluxoException
    {
        $f = new FluxoException($etapa, $feedback, $feedback, $payload);
        $f->etapasConcluidas = $etapas;
        return $f;
    }

    /**
     * Cria (RA = 0) ou atualiza o aluno. Vincula Cliente/Fornecedor
     * existente (mesmo CPF/RNM) quando houver.
     * Retorna a chave "CODCOLIGADA;RA".
     */
    public function salvar(
        string|int $codPessoa,
        string|int $codColigada,
        string|int $codTipoCurso,
        string|int $codFilial,
        string $cpf = '',
        string $rnm = ''
    ): string {
        $existente = $this->consulta->aluno($codPessoa, $codColigada);
        $ra = $existente['RA'] ?? 0;

        $cliFor = $this->consulta->cliForPorCpfRnm($cpf, $rnm);
        $cliForXml = '';
        if ($cliFor !== null) {
            $cliForXml = <<<XML
                <CODCOLCFO>{$cliFor['CODCOLCFO']}</CODCOLCFO>
                <CODCFO>{$cliFor['CODCFO']}</CODCFO>
            XML;
        }

        $xml = <<<XML
        <EduAluno>
            <SAluno>
                <CODCOLIGADA>{$codColigada}</CODCOLIGADA>
                <RA>{$ra}</RA>
                {$cliForXml}
                <CODPESSOA>{$codPessoa}</CODPESSOA>
                <CODTIPOCURSO>{$codTipoCurso}</CODTIPOCURSO>
            </SAluno>
            <SAlunoCompl>
                <CODCOLIGADA>{$codColigada}</CODCOLIGADA>
                <RA>{$ra}</RA>
            </SAlunoCompl>
        </EduAluno>
        XML;

        $contexto = $this->contexto($codColigada, $codTipoCurso, $codFilial);
        $result = $this->rm->saveRecord(self::DATASERVER_ALUNO, $xml, $contexto);

        $parts = explode(';', $result, 2);
        if ($parts[0] != $codColigada) {
            throw new RMException(
                'O RM rejeitou a gravação do aluno',
                operacao: 'SaveRecord',
                dataServer: self::DATASERVER_ALUNO,
                contexto: $contexto,
                xmlEnviado: $xml,
                retornoRm: $result
            );
        }

        return $result;
    }

    /**
     * Garante o vínculo do usuário do aluno com a filial (ACESSO = 2).
     */
    public function garantirUsuarioFilial(
        string $codUsuario,
        string|int $codColigada,
        string|int $codTipoCurso,
        string|int $codFilial
    ): string {
        $xml = <<<XML
        <EduUsuarioFilial>
            <SUsuarioFilial>
                <CODCOLIGADA>{$codColigada}</CODCOLIGADA>
                <CODTIPOCURSO>{$codTipoCurso}</CODTIPOCURSO>
                <CODFILIAL>{$codFilial}</CODFILIAL>
                <CODUSUARIO>{$codUsuario}</CODUSUARIO>
                <ACESSO>2</ACESSO>
            </SUsuarioFilial>
        </EduUsuarioFilial>
        XML;

        return $this->rm->saveRecord(
            self::DATASERVER_USUARIO_FILIAL,
            $xml,
            $this->contexto($codColigada, $codTipoCurso, $codFilial)
        );
    }

    /**
     * Verifica se o usuário ainda usa a senha padrão (AutenticaAcesso).
     */
    public function temSenhaPadrao(string $codUsuario, string $senhaPadrao): bool
    {
        try {
            return $this->rm->autenticaAcesso($codUsuario, $senhaPadrao);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Reativa o usuário com a senha padrão sem forçar troca
     * (necessário para o SSO de primeiro acesso).
     */
    public function ajustarAcessoUsuario(string $codUsuario, string $senhaPadrao): void
    {
        $xml = <<<XML
        <GlbUsuario>
            <GUSUARIO>
                <CODUSUARIO>{$codUsuario}</CODUSUARIO>
                <STATUS>1</STATUS>
                <SENHA>{$senhaPadrao}</SENHA>
                <OBRIGAALTERARSENHA>F</OBRIGAALTERARSENHA>
            </GUSUARIO>
        </GlbUsuario>
        XML;

        $result = $this->rm->saveRecord(self::DATASERVER_USUARIO, $xml);

        if ($result !== $codUsuario) {
            throw new RMException(
                'Houve um erro ao ajustar acesso do usuário',
                operacao: 'SaveRecord',
                dataServer: self::DATASERVER_USUARIO,
                xmlEnviado: $xml,
                retornoRm: $result
            );
        }
    }
}
