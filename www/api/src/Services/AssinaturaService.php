<?php

declare(strict_types=1);

namespace FMP\RMApi\Services;

use FMP\RMApi\Clients\RMSoapClient;
use FMP\RMApi\Exceptions\RMException;
use FMP\RMApi\Exceptions\ValidationException;
use FMP\RMApi\Support\ProcessXml;

/**
 * Envio do contrato do aluno para o TAE — TOTVS Assinatura Eletrônica
 * (processo EduTotvsSignContratoSliceableProcData, via
 * wsProcess/ExecuteWithXMLParams). Pré-requisitos do lado do RM/TAE
 * (permissão, e-mails únicos por assinante, conferência do status) estão
 * no API.md.
 *
 * Mesma ergonomia da geração de lançamentos: RA + OFERTA resolvem coligada,
 * filial, período letivo e tipo de curso (INT.EDUVEM.00006); o contrato vem
 * do corpo ou da matrícula no período letivo (INT.EDUVEM.00014).
 *
 * NÃO é idempotente: cada chamada real dispara um novo envio para assinatura.
 * O processo roda no Monitor de Jobs (assíncrono) e a API não tem como
 * confirmar que o documento chegou ao aluno — devolve o retorno do RM
 * (JobId) e, quando a sentença INT.EDUVEM.00021 existe, o log do job.
 */
class AssinaturaService
{
    public const PROCESSO = 'EduTotvsSignContratoSliceableProcData';

    public function __construct(
        private readonly RMSoapClient $rm,
        private readonly ConsultaService $consulta,
        private readonly array $rmConfig
    ) {
    }

    /**
     * Campos de entrada ($in):
     *  - RA                (obrig.)
     *  - OFERTA            (obrig.) resolve coligada/filial/período letivo/tipo de curso
     *  - NOMEDOCUMENTO     (obrig.) nome do documento no TAE
     *  - IDREPORT          (obrig.) id do relatório do contrato no RM Reports
     *  - CODCOLIGADAREPORT (obrig.) coligada do relatório (0 = global)
     *  - CODCONTRATO       (opc.)   sem ele, resolve pela matrícula no período letivo
     *  - DRY_RUN           (opc.)   true = devolve o XML gerado sem enviar ao RM
     *
     * @return array<string,mixed>
     * @throws ValidationException dados de entrada inválidos / oferta ou contrato não localizados
     * @throws RMException         falha do RM ao executar o processo
     */
    public function enviar(array $in): array
    {
        $req = function (string $chave) use ($in): string {
            $v = trim((string) ($in[$chave] ?? ''));
            if ($v === '') {
                throw new ValidationException(
                    "Informe o campo obrigatório {$chave} para enviar o contrato para assinatura.",
                    "Assinatura: campo obrigatório ausente ({$chave})",
                    $in
                );
            }
            return $v;
        };

        $ra            = $req('RA');
        $offer         = $req('OFERTA');
        $nomeDocumento = $req('NOMEDOCUMENTO');
        // Relatório do RM Reports que gera o PDF. Só do corpo, sem default:
        // um id errado manda ao aluno o PDF de outro relatório.
        $idRelatorio          = $req('IDREPORT');
        $codColigadaRelatorio = $req('CODCOLIGADAREPORT');

        // DRY_RUN é trava de segurança: valor irreconhecível ("sim") é erro,
        // nunca um envio real silencioso (mesma regra da baixa).
        $dryRun = false;
        if (array_key_exists('DRY_RUN', $in) && $in['DRY_RUN'] !== null && $in['DRY_RUN'] !== '') {
            $dryRun = filter_var($in['DRY_RUN'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($dryRun === null) {
                throw new ValidationException(
                    'DRY_RUN deve ser true ou false. Valor não reconhecido seria interpretado '
                        . 'como envio REAL, então a requisição foi recusada.',
                    'Assinatura: DRY_RUN inválido',
                    $in
                );
            }
        }

        $oferta = $this->consulta->oferta($offer);
        if ($oferta === null) {
            throw new ValidationException(
                "Oferta '{$offer}' não encontrada.",
                'Assinatura: oferta inexistente',
                ['OFERTA' => $offer]
            );
        }

        $codContrato = trim((string) ($in['CODCONTRATO'] ?? ''));
        if ($codContrato === '') {
            $pl = $this->consulta->matriculaPeriodoLetivo($offer, $ra);
            if ($pl === null || empty($pl['CODCONTRATO'])) {
                throw new ValidationException(
                    "Não foi possível localizar o contrato do aluno (RA {$ra}) nesta oferta. "
                        . 'Envie CODCONTRATO no corpo ou faça a matrícula no período letivo antes.',
                    'Assinatura: contrato não informado/localizado',
                    ['RA' => $ra, 'OFERTA' => $offer]
                );
            }
            $codContrato = (string) $pl['CODCONTRATO'];
        }

        // O builder recusa valor não inteiro (do corpo ou da oferta): vira
        // 422 com a mensagem do campo, em vez de 500.
        try {
            $xml = ProcessXml::assinaturaContrato(
                codColigada: (string) ($oferta['CODCOLIGADA'] ?? ''),
                codFilial: (string) ($oferta['CODFILIAL'] ?? ''),
                codTipoCurso: (string) ($oferta['CODTIPOCURSO'] ?? ''),
                idPerlet: (string) ($oferta['IDPERLET'] ?? ''),
                ra: $ra,
                codContrato: $codContrato,
                codColigadaRelatorio: $codColigadaRelatorio,
                idRelatorio: $idRelatorio,
                nomeDocumento: $nomeDocumento,
                codUsuario: (string) ($this->rmConfig['usuario_servico'] ?? 'integra.eduvem')
            );
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException(
                $e->getMessage(),
                'Assinatura: valor inválido para o XML do processo',
                $in
            );
        }

        $resumo = [
            'RA'          => $ra,
            'OFERTA'      => $offer,
            'CODCONTRATO' => $codContrato,
            'IDREPORT'    => $idRelatorio,
            'PROCESSO'    => self::PROCESSO,
        ];

        if ($dryRun) {
            return $resumo + [
                'dry_run'   => true,
                'OPERACAO'  => 'ExecuteWithXMLParams',
                'ws_url'    => (string) ($this->rmConfig['ws_url'] ?? ''),
                'xml_bytes' => strlen($xml),
                'xml_md5'   => md5($xml),
                'xml'       => $xml,
            ];
        }

        $retorno = $this->rm->executeWithXmlParams(self::PROCESSO, $xml);

        // Retorno numérico diferente de "1" = JobId: anexa o log do job
        // (Monitor de Jobs) quando a sentença INT.EDUVEM.00021 existe.
        $logJob = null;
        if (is_numeric($retorno) && $retorno !== '1') {
            $logJob = $this->consulta->logProcessoFormatado($retorno);
        }

        return $resumo + [
            'retorno_rm' => $retorno,
            'log_job'    => $logJob,
        ];
    }
}
