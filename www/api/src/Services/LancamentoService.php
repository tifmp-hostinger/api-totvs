<?php

declare(strict_types=1);

namespace FMP\RMApi\Services;

use FMP\RMApi\Clients\RMSoapClient;
use FMP\RMApi\Exceptions\RMException;
use FMP\RMApi\Exceptions\ValidationException;
use FMP\RMApi\Support\ProcessXml;

/**
 * Lançamentos financeiros a partir do contrato
 * (processo EduGerarLancFromContratoSliceableData).
 */
class LancamentoService
{
    public const PROCESSO = 'EduGerarLancFromContratoSliceableData';

    public function __construct(
        private readonly RMSoapClient $rm,
        private readonly ConsultaService $consulta
    ) {
    }

    /**
     * Gera os lançamentos do contrato. Idempotente: se já existem, não regera.
     * O processo roda via JobMonitor e pode concluir de forma assíncrona,
     * então a confirmação é feita com retentativas. Em caso de falha, o log
     * completo do job (detalhes/erros/parâmetros/resumo) é anexado ao erro.
     *
     * Retorna ['gerados' => bool, 'ja_existiam' => bool].
     */
    /**
     * Geração de lançamentos a partir de RA + OFERTA (rota autônoma, fora do
     * fluxo de inscrição). Resolve a oferta (INT.EDUVEM.00006) e delega para
     * gerar(). O contrato pode vir pronto em $codContrato (do corpo) ou, se
     * vazio, é resolvido pela matrícula no período letivo (INT.EDUVEM.00014).
     *
     * @return array{gerados:bool, ja_existiam:bool, CODCONTRATO:string, RA:string, OFERTA:string}
     * @throws ValidationException oferta inexistente ou aluno sem contrato
     * @throws RMException          falha do RM ao gerar
     */
    public function gerarPorRaOferta(string $ra, string $offer, string $codContrato = ''): array
    {
        $oferta = $this->consulta->oferta($offer);
        if ($oferta === null) {
            throw new ValidationException(
                "Oferta '{$offer}' não encontrada.",
                'Geração de lançamentos: oferta inexistente',
                ['OFERTA' => $offer]
            );
        }

        $codContrato = trim($codContrato);
        if ($codContrato === '') {
            $pl = $this->consulta->matriculaPeriodoLetivo($offer, $ra);
            if ($pl === null || empty($pl['CODCONTRATO'])) {
                throw new ValidationException(
                    "Não foi possível localizar o contrato do aluno (RA {$ra}) nesta oferta. "
                        . 'Envie CODCONTRATO no corpo ou faça a matrícula no período letivo antes.',
                    'Geração de lançamentos: contrato não informado/localizado',
                    ['RA' => $ra, 'OFERTA' => $offer]
                );
            }
            $codContrato = (string) $pl['CODCONTRATO'];
        }

        $res = $this->gerar(
            $oferta['CODCOLIGADA'],
            $oferta['CODFILIAL'],
            $oferta['IDPERLET'],
            $ra,
            $codContrato
        );

        return $res + ['CODCONTRATO' => $codContrato, 'RA' => $ra, 'OFERTA' => $offer];
    }

    /**
     * Busca (somente leitura) os lançamentos do contrato do aluno, resolvendo
     * oferta e contrato a partir de RA + OFERTA — mesma ergonomia de
     * gerarPorRaOferta(). Usa a sentença INT.EDUVEM.00018.
     *
     * As COLUNAS retornadas são as da sentença cadastrada no RM (a API não as
     * reescreve). Além das linhas cruas em `lancamentos`, devolvemos um
     * `resumo` com os campos localizados de forma tolerante (nomes de coluna
     * variam entre instalações) — útil para encadear a baixa sem adivinhação:
     * quando o IDLAN é localizável, vem em `resumo.IDLANS` / `resumo.ABERTOS`.
     *
     * @return array{total:int, lancamentos:array, resumo:array, CODCONTRATO:string, RA:string, OFERTA:string}
     * @throws ValidationException oferta inexistente ou aluno sem contrato
     */
    public function buscarPorRaOferta(string $ra, string $offer, string $codContrato = ''): array
    {
        $oferta = $this->consulta->oferta($offer);
        if ($oferta === null) {
            throw new ValidationException(
                "Oferta '{$offer}' não encontrada.",
                'Busca de lançamentos: oferta inexistente',
                ['OFERTA' => $offer]
            );
        }

        $codContrato = trim($codContrato);
        if ($codContrato === '') {
            $pl = $this->consulta->matriculaPeriodoLetivo($offer, $ra);
            if ($pl === null || empty($pl['CODCONTRATO'])) {
                throw new ValidationException(
                    "Não foi possível localizar o contrato do aluno (RA {$ra}) nesta oferta. "
                        . 'Envie CODCONTRATO na query ou faça a matrícula no período letivo antes.',
                    'Busca de lançamentos: contrato não informado/localizado',
                    ['RA' => $ra, 'OFERTA' => $offer]
                );
            }
            $codContrato = (string) $pl['CODCONTRATO'];
        }

        $linhas = $this->consulta->lancamentos(
            $oferta['CODCOLIGADA'],
            $oferta['IDPERLET'],
            $codContrato,
            $ra
        );

        return [
            'total'       => count($linhas),
            'lancamentos' => $linhas,
            'resumo'      => self::resumir($linhas),
            'CODCONTRATO' => $codContrato,
            'RA'          => $ra,
            'OFERTA'      => $offer,
        ];
    }

    /**
     * Extrai, de forma tolerante a nomes de coluna, os dados úteis para
     * encadear uma baixa. Nunca lança: se a sentença não expuser a coluna,
     * o campo simplesmente vem vazio/null.
     *
     * @param array<int,array<string,mixed>> $linhas
     * @return array<string,mixed>
     */
    private static function resumir(array $linhas): array
    {
        // Aliases observados/plausíveis; a primeira coluna existente vence.
        $achar = static function (array $linha, array $candidatos): mixed {
            foreach ($linha as $coluna => $valor) {
                if (in_array(strtoupper(trim((string) $coluna)), $candidatos, true)) {
                    return $valor;
                }
            }
            return null;
        };

        $idLans = [];
        $abertos = [];
        $valorTotal = 0.0;
        $temValor = false;

        foreach ($linhas as $linha) {
            if (!is_array($linha)) {
                continue;
            }

            $idLan  = $achar($linha, ['IDLAN', 'ID_LAN', 'IDLANCAMENTO']);
            $valor  = $achar($linha, ['VALORLIQUIDO', 'VALOR_LIQUIDO', 'VALOR', 'VALORORIGINAL']);
            $status = $achar($linha, ['STATUSLAN', 'STATUS', 'SITUACAO']);
            $baixado = $achar($linha, ['VALORBAIXADO', 'VALOR_BAIXADO', 'DATABAIXA']);

            if ($idLan !== null && $idLan !== '') {
                $idLans[] = (string) $idLan;
            }

            if ($valor !== null && is_numeric(str_replace(',', '.', (string) $valor))) {
                $valorTotal += (float) str_replace(',', '.', (string) $valor);
                $temValor = true;
            }

            // "Em aberto" quando o status não indica baixa e não há valor baixado.
            $statusTxt = mb_strtoupper(trim((string) ($status ?? '')));
            $pareceBaixado = $statusTxt !== '' && (
                str_contains($statusTxt, 'BAIX') || str_contains($statusTxt, 'QUITA') || $statusTxt === '1'
            );
            $temBaixa = $baixado !== null && $baixado !== '' && $baixado !== '0'
                && $baixado !== '0.00' && !str_starts_with((string) $baixado, '0001-01-01');

            if ($idLan !== null && $idLan !== '' && !$pareceBaixado && !$temBaixa) {
                $abertos[] = ['IDLAN' => (string) $idLan, 'VALOR' => $valor, 'STATUS' => $status];
            }
        }

        return [
            'IDLANS'        => $idLans,
            'ABERTOS'       => $abertos,
            'TOTAL_ABERTOS' => count($abertos),
            'PRIMEIRO_IDLAN' => $idLans[0] ?? null,
            'VALOR_TOTAL'   => $temValor ? number_format($valorTotal, 2, '.', '') : null,
            'COLUNAS'       => isset($linhas[0]) && is_array($linhas[0]) ? array_keys($linhas[0]) : [],
        ];
    }

    public function gerar(
        string|int $codColigada,
        string|int $codFilial,
        string|int $idPerlet,
        string $ra,
        string $codContrato
    ): array {
        $existentes = $this->consulta->lancamentos($codColigada, $idPerlet, $codContrato, $ra);

        if (count($existentes) > 0) {
            return ['gerados' => false, 'ja_existiam' => true];
        }

        $xml = ProcessXml::gerarLancamento(
            codColigada: $codColigada,
            codFilial: $codFilial,
            idPerlet: $idPerlet,
            ra: $ra,
            codContrato: $codContrato
        );

        $resultado = $this->rm->executeWithXmlParams(self::PROCESSO, $xml);

        // Confirma a geração com retentativas (job pode ser assíncrono)
        $gerados = [];
        for ($tentativa = 1; $tentativa <= 6; $tentativa++) {
            $gerados = $this->consulta->lancamentos($codColigada, $idPerlet, $codContrato, $ra);
            if (count($gerados) > 0) {
                break;
            }
            sleep(2);
        }

        if (count($gerados) === 0) {
            // Resultado numérico diferente de 1 = JobId: busca o log do job no RM
            $logJob = null;
            if (is_numeric($resultado) && $resultado !== '1') {
                $logJob = $this->consulta->logProcessoFormatado($resultado);
            }

            $detalheLog = $logJob !== null
                ? "\n\n{$logJob}"
                : ' Cadastre a sentença ' . ConsultaService::SQL_LOG_PROCESSO
                    . ' no RM (ver API.md) para a API anexar automaticamente o log do job'
                    . ' (detalhes, erros, parâmetros e resumo do Monitor de Jobs).';

            throw new RMException(
                'Erro ao gerar lançamento financeiro',
                operacao: 'ExecuteWithXMLParams',
                dataServer: self::PROCESSO,
                xmlEnviado: $xml,
                retornoRm: "Retorno do processo 'Gerar lançamento': {$resultado}. "
                    . 'Os lançamentos não foram localizados pela consulta '
                    . ConsultaService::SQL_LANCAMENTOS . ' após 6 tentativas (~12s).'
                    . $detalheLog
            );
        }

        return ['gerados' => true, 'ja_existiam' => false];
    }
}
