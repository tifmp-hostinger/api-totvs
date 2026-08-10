<?php

declare(strict_types=1);

namespace FMP\RMApi\Services;

use FMP\RMApi\Exceptions\ValidationException;
use Throwable;

/**
 * Ficha 360º do Aluno — agrega, em uma única resposta, tudo que a integração
 * consegue extrair do RM sobre um aluno, encadeando as consultas existentes:
 *
 *   Cadastro (RhuPessoaData / ReadRecord)
 *     → Acadêmico + acesso ao portal (INT.EDUVEM.00008)
 *     → Financeiro / Cliente-Fornecedor (INT.EDUVEM.00009)
 *     → [se OFERTA informada] Matrícula no curso (00011),
 *        Contrato / período letivo (00014) e Lançamentos financeiros (00018)
 *
 * Ponto de partida flexível: CPF, RNM ou código da pessoa. Cada seção é
 * resolvida de forma tolerante a falha — uma sentença ausente ou um bloco
 * sem dados vira status NAO_ENCONTRADO/INDISPONIVEL, nunca derruba a ficha.
 * Só a identificação da pessoa é obrigatória (404 quando não existe).
 */
final class FichaAlunoService
{
    public function __construct(
        private readonly PessoaService $pessoa,
        private readonly ConsultaService $consulta
    ) {
    }

    /**
     * @param array{cpf?:string,rnm?:string,codpessoa?:string} $filtro
     * @return array<string,mixed>
     * @throws ValidationException quando nenhum identificador é informado
     */
    public function montar(string|int $codColigada, array $filtro, string $oferta = ''): ?array
    {
        $cpf = self::soDigitos((string) ($filtro['cpf'] ?? ''));
        $rnm = trim((string) ($filtro['rnm'] ?? ''));
        $codPessoa = trim((string) ($filtro['codpessoa'] ?? ''));

        if ($cpf === '' && $rnm === '' && $codPessoa === '') {
            throw new ValidationException(
                'Informe CPF, RNM ou o código da pessoa para montar a ficha.',
                'Ficha 360: nenhum identificador informado',
                $filtro
            );
        }

        /* ---------- 1. Localiza a pessoa (cadastro civil) ---------- */
        $cadastro = null;
        if ($codPessoa !== '') {
            $cadastro = $this->pessoa->buscar($codPessoa);
        } elseif ($cpf !== '' || $rnm !== '') {
            $cadastro = $this->pessoa->buscarPorCpfRnm($cpf, $rnm);
        }

        if ($cadastro === null) {
            return null; // controller devolve 404
        }

        $codPessoa = (string) self::pick($cadastro, 'CODIGO', 'CODPESSOA') ?: $codPessoa;
        if ($cpf === '') {
            $cpf = self::soDigitos((string) self::pick($cadastro, 'CPF'));
        }

        $secoes = [];
        $secoes[] = [
            'chave'   => 'cadastro',
            'titulo'  => 'Cadastro — dados civis e contato',
            'fonte'   => 'RhuPessoaData (ReadRecord)',
            'status'  => 'OK',
            'formato' => 'ficha',
            'dados'   => $cadastro,
        ];

        /* ---------- 2. Acadêmico + acesso ao portal ---------- */
        $aluno = $this->tolerante(fn() => $this->consulta->aluno($codPessoa, $codColigada));
        $secoes[] = self::secaoFicha(
            'academico',
            'Acadêmico e acesso ao portal',
            'INT.EDUVEM.00008',
            $aluno['dados'] ?? null,
            $aluno['erro'] ?? null,
            'Esta pessoa ainda não possui cadastro de aluno nesta coligada.'
        );
        $dadosAluno = $aluno['dados'] ?? null;
        $ra = $dadosAluno !== null ? (string) self::pick($dadosAluno, 'RA') : '';

        /* ---------- 3. Financeiro (Cliente/Fornecedor) ---------- */
        $cfo = ($cpf !== '' || $rnm !== '')
            ? $this->tolerante(fn() => $this->consulta->cliForPorCpfRnm($cpf, $rnm))
            : ['dados' => null, 'erro' => null];
        $secoes[] = self::secaoFicha(
            'financeiro',
            'Financeiro — cliente/fornecedor',
            'INT.EDUVEM.00009',
            $cfo['dados'] ?? null,
            $cfo['erro'] ?? null,
            'Nenhum cliente/fornecedor vinculado ao documento (necessário para contratos/lançamentos).'
        );
        $dadosCfo = $cfo['dados'] ?? null;

        /* ---------- 4. Matrícula na oferta (opcional) ---------- */
        $oferta = trim($oferta);
        if ($oferta !== '') {
            $this->anexarMatricula($secoes, $codColigada, $ra, $oferta);
        }

        return [
            'identificacao' => [
                'CODPESSOA'   => $codPessoa,
                'NOME'        => (string) self::pick($cadastro, 'NOME'),
                'DOCUMENTO'   => $cpf !== '' ? self::mascararCpf($cpf) : ($rnm !== '' ? $rnm : '—'),
                'RA'          => $ra !== '' ? $ra : null,
                'CODUSUARIO'  => $dadosAluno !== null ? (self::pick($dadosAluno, 'CODUSUARIO') ?: null) : null,
                'CODCOLIGADA' => (string) $codColigada,
            ],
            'kpis'   => self::montarKpis($dadosAluno, $dadosCfo, $secoes),
            'secoes' => $secoes,
        ];
    }

    /**
     * Enriquece a ficha com matrícula no curso, contrato e lançamentos da
     * oferta informada. Depende do RA (aluno já matriculado).
     *
     * @param array<int,array<string,mixed>> $secoes
     */
    private function anexarMatricula(array &$secoes, string|int $codColigada, string $ra, string $oferta): void
    {
        if ($ra === '') {
            $secoes[] = [
                'chave'  => 'matricula', 'titulo' => "Matrícula na oferta {$oferta}",
                'fonte'  => 'INT.EDUVEM.00011 / 00014 / 00018', 'status' => 'NAO_CONSULTADO',
                'formato' => 'aviso',
                'aviso'  => 'Sem RA (a pessoa ainda não é aluno), não há matrícula a consultar nesta oferta.',
            ];
            return;
        }

        // 4a. Matrícula no curso
        $curso = $this->tolerante(fn() => $this->consulta->matriculaCurso($oferta, $ra));
        $secoes[] = self::secaoFicha(
            'matricula_curso',
            "Matrícula no curso — {$oferta}",
            'INT.EDUVEM.00011',
            $curso['dados'] ?? null,
            $curso['erro'] ?? null,
            'Aluno não matriculado no curso desta oferta.'
        );

        // 4b. Contrato / período letivo
        $pl = $this->tolerante(fn() => $this->consulta->matriculaPeriodoLetivo($oferta, $ra));
        $contrato = $pl['dados'] ?? null;
        $secoes[] = self::secaoFicha(
            'contrato',
            "Contrato / período letivo — {$oferta}",
            'INT.EDUVEM.00014',
            $contrato,
            $pl['erro'] ?? null,
            'Aluno não matriculado no período letivo (sem contrato) desta oferta.'
        );

        // 4c. Lançamentos financeiros (dependem das chaves do contrato)
        if ($contrato !== null) {
            $codContrato = (string) self::pick($contrato, 'CODCONTRATO');
            $idPerlet    = (string) self::pick($contrato, 'IDPERLET');
            $colContrato = (string) (self::pick($contrato, 'CODCOLIGADA') ?: $codColigada);

            if ($codContrato !== '' && $idPerlet !== '') {
                $lanc = $this->tolerante(
                    fn() => $this->consulta->lancamentos($colContrato, $idPerlet, $codContrato, $ra)
                );
                $linhas = is_array($lanc['dados'] ?? null) ? $lanc['dados'] : [];
                $secoes[] = [
                    'chave'   => 'lancamentos',
                    'titulo'  => 'Lançamentos financeiros',
                    'fonte'   => 'INT.EDUVEM.00018',
                    'status'  => $lanc['erro'] !== null ? 'INDISPONIVEL' : (count($linhas) ? 'OK' : 'NAO_ENCONTRADO'),
                    'formato' => 'tabela',
                    'dados'   => $linhas,
                    'aviso'   => $lanc['erro'] ?? (count($linhas) ? null : 'Contrato sem lançamentos financeiros registrados.'),
                ];
            }
        }
    }

    /* ================= helpers ================= */

    /**
     * Executa uma consulta ao RM tolerando falha: devolve o dado ou o texto
     * do erro, sem propagar exceção (uma seção quebrada não derruba a ficha).
     *
     * @return array{dados:mixed,erro:?string}
     */
    private function tolerante(callable $fn): array
    {
        try {
            return ['dados' => $fn(), 'erro' => null];
        } catch (Throwable $e) {
            return ['dados' => null, 'erro' => 'Não foi possível consultar no RM: ' . $e->getMessage()];
        }
    }

    /**
     * Monta uma seção de ficha (objeto único). Deriva o status do resultado.
     *
     * @return array<string,mixed>
     */
    private static function secaoFicha(
        string $chave,
        string $titulo,
        string $fonte,
        ?array $dados,
        ?string $erro,
        string $avisoVazio
    ): array {
        if ($erro !== null) {
            $status = 'INDISPONIVEL';
            $aviso  = $erro;
        } elseif ($dados === null || $dados === []) {
            $status = 'NAO_ENCONTRADO';
            $aviso  = $avisoVazio;
        } else {
            $status = 'OK';
            $aviso  = null;
        }

        return [
            'chave'   => $chave,
            'titulo'  => $titulo,
            'fonte'   => $fonte,
            'status'  => $status,
            'formato' => 'ficha',
            'dados'   => $dados,
            'aviso'   => $aviso,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $secoes
     * @return array<int,array<string,string>>
     */
    private static function montarKpis(?array $aluno, ?array $cfo, array $secoes): array
    {
        $ehAluno   = $aluno !== null && self::pick($aluno, 'RA') !== null;
        $temUsuario = $aluno !== null && (string) self::pick($aluno, 'CODUSUARIO') !== '';
        $ultimoAcesso = $aluno !== null ? (string) self::pick($aluno, 'DATAULTIMOACESSOVALIDO') : '';
        $jaAcessou = $ultimoAcesso !== '';

        $kpis = [
            [
                'rotulo' => 'Situação',
                'valor'  => $ehAluno ? 'Aluno cadastrado' : 'Sem cadastro de aluno',
                'tom'    => $ehAluno ? 'ok' : 'neutro',
            ],
            [
                'rotulo' => 'RA',
                'valor'  => $ehAluno ? (string) self::pick($aluno, 'RA') : '—',
                'tom'    => 'neutro',
            ],
            [
                'rotulo' => 'Acesso ao portal',
                'valor'  => $jaAcessou ? 'Já acessou' : ($temUsuario ? 'Nunca acessou' : 'Sem usuário'),
                'tom'    => $jaAcessou ? 'ok' : ($temUsuario ? 'warn' : 'neutro'),
            ],
            [
                'rotulo' => 'Financeiro (CFO)',
                'valor'  => $cfo !== null ? ('CODCFO ' . self::pick($cfo, 'CODCFO')) : 'Não vinculado',
                'tom'    => $cfo !== null ? 'ok' : 'warn',
            ],
        ];

        // KPI extra: nº de lançamentos, se a seção veio na ficha.
        foreach ($secoes as $s) {
            if (($s['chave'] ?? '') === 'lancamentos' && is_array($s['dados'] ?? null)) {
                $kpis[] = [
                    'rotulo' => 'Lançamentos',
                    'valor'  => (string) count($s['dados']),
                    'tom'    => count($s['dados']) ? 'ok' : 'neutro',
                ];
            }
        }

        return $kpis;
    }

    private static function pick(array $arr, string ...$chaves): mixed
    {
        // Busca case-insensitive por qualquer uma das chaves; ignora vazio.
        $mapa = [];
        foreach ($arr as $k => $v) {
            $mapa[strtoupper((string) $k)] = $v;
        }
        foreach ($chaves as $chave) {
            $v = $mapa[strtoupper($chave)] ?? null;
            if ($v !== null && $v !== '') {
                return $v;
            }
        }
        return null;
    }

    private static function soDigitos(string $v): string
    {
        return preg_replace('/\D/', '', $v) ?? '';
    }

    private static function mascararCpf(string $cpf): string
    {
        if (strlen($cpf) !== 11) {
            return $cpf;
        }
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
}
