<?php

declare(strict_types=1);

namespace FMP\RMApi\Services;

use FMP\RMApi\Clients\RMSoapClient;
use FMP\RMApi\Exceptions\RMException;
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
        private readonly array $rmConfig
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
