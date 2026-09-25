<?php

declare(strict_types=1);

/**
 * Envio do contrato para a TOTVS Assinatura Eletrônica
 * (processo EduTotvsSignContratoSliceableProcData).
 *
 * O template é o XML que a fórmula visual do RM preenche antes de chamar o
 * processo. Estes testes garantem que: cada marcador do XML original
 * recebe o valor certo no ponto certo, os restos da sessão capturada somem,
 * os valores de entrada não injetam markup e o service não dispara o
 * processo quando a entrada é inválida ou é DRY_RUN.
 */

use FMP\RMApi\Clients\RMSoapClient;
use FMP\RMApi\Exceptions\ValidationException;
use FMP\RMApi\Services\AssinaturaService;
use FMP\RMApi\Services\ConsultaService;
use FMP\RMApi\Support\ProcessXml;

$montar = static fn(array $o = []) => ProcessXml::assinaturaContrato(
    codColigada: $o['col'] ?? 1,
    codFilial: $o['fil'] ?? 3,
    codTipoCurso: $o['tipo'] ?? 2,
    idPerlet: $o['perlet'] ?? 77,
    ra: $o['ra'] ?? '24001268',
    codContrato: $o['contrato'] ?? 'CT-0001',
    codColigadaRelatorio: $o['colrel'] ?? 0,
    idRelatorio: $o['idrel'] ?? 4321,
    nomeDocumento: $o['nome'] ?? 'Contrato de Matrícula',
    codUsuario: $o['usuario'] ?? 'integra.eduvem'
);

/** XPath sobre o XML gerado (troca a declaração utf-16 só para o parse). */
$xp = static function (string $xml): \DOMXPath {
    $doc = new \DOMDocument();
    $doc->loadXML(preg_replace('/encoding="utf-16"/i', 'encoding="utf-8"', $xml, 1));
    $x = new \DOMXPath($doc);
    $x->registerNamespace('rm', 'http://www.totvs.com.br/RM/');
    $x->registerNamespace('t', 'http://www.totvs.com/');
    $x->registerNamespace('arr', 'http://schemas.microsoft.com/2003/10/Serialization/Arrays');
    $x->registerNamespace('i', 'http://www.w3.org/2001/XMLSchema-instance');
    return $x;
};
$texto = static fn(\DOMXPath $x, string $q): ?string => ($n = $x->query($q)->item(0)) ? $n->textContent : null;
$contexto = static fn(\DOMXPath $x, string $chave): ?string => $texto(
    $x,
    "//t:Context/rm:_params/arr:KeyValueOfanyTypeanyType[arr:Key='{$chave}']/arr:Value"
);

/* =====================================================================
 * Builder
 * ===================================================================== */

$xml = $montar();
check('assinatura: XML bem-formado', xmlBemFormado($xml));
check('assinatura: declaração utf-16 é o primeiro byte', str_starts_with($xml, '<?xml version="1.0" encoding="utf-16"?>'));
check('assinatura: nenhum placeholder {{...}} sobrando', !str_contains($xml, '{{'));
check('assinatura: nenhum marcador [CAMPO] do XML original sobrando', !preg_match('/\[[A-Z]+\]/', $xml));

$x = $xp($xml);
check('assinatura: raiz EduTotvsSignSliceableParamsProc', $x->query('/rm:EduTotvsSignSliceableParamsProc')->length === 1);
checkSame('assinatura: ServerName', 'EduTotvsSignContratoSliceableProcData', $texto($x, '/rm:*/t:ServerName'));

// Chave do contrato (SCONTRATO): ordem e tipos exatamente como no XML real.
$pk = [];
foreach ($x->query('//t:PrimaryKeyList/arr:ArrayOfanyType/arr:anyType') as $n) {
    $pk[] = [$n->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'type'), $n->textContent];
}
checkSame('assinatura: PrimaryKeyList (CODCOLIGADA, RA, IDPERLET, CODCONTRATO)', [
    ['b:short', '1'],
    ['b:string', '24001268'],
    ['b:int', '77'],
    ['b:string', 'CT-0001'],
], $pk);
checkSame('assinatura: PrimaryKeyTableName', 'SCONTRATO', $texto($x, '/rm:*/t:PrimaryKeyTableName'));

checkSame('assinatura: contexto $CODCOLIGADA', '1', $contexto($x, '$CODCOLIGADA'));
checkSame('assinatura: contexto $CODFILIAL', '3', $contexto($x, '$CODFILIAL'));
checkSame('assinatura: contexto $CODTIPOCURSO', '2', $contexto($x, '$CODTIPOCURSO'));
checkSame('assinatura: contexto $CODUSUARIO', 'integra.eduvem', $contexto($x, '$CODUSUARIO'));

checkSame('assinatura: CodColigada', '1', $texto($x, '/rm:*/rm:CodColigada'));
checkSame('assinatura: CodFilial', '3', $texto($x, '/rm:*/rm:CodFilial'));
checkSame('assinatura: CodTipoCurso', '2', $texto($x, '/rm:*/rm:CodTipoCurso'));
checkSame('assinatura: CodColigadaRelatorio', '0', $texto($x, '/rm:*/rm:CodColigadaRelatorio'));
checkSame('assinatura: IdRelatorioRMReports', '4321', $texto($x, '/rm:*/rm:IdRelatorioRMReports'));
checkSame('assinatura: NomeDocumento', 'Contrato de Matrícula', $texto($x, '/rm:*/rm:NomeDocumento'));
checkSame('assinatura: CodUsuario', 'integra.eduvem', $texto($x, '/rm:*/t:CodUsuario'));
checkSame('assinatura: UserName', 'integra.eduvem', $texto($x, '/rm:*/t:UserName'));

// Fixos do XML real (não mudam por chamada).
checkSame('assinatura: Operacao fixa', 'EnviarDocumento', $texto($x, '/rm:*/rm:Operacao'));
checkSame('assinatura: TipoDocumento fixo', 'RMReports', $texto($x, '/rm:*/rm:TipoDocumento'));
checkSame('assinatura: assinante = aluno', 'true', $texto($x, '/rm:*/rm:EnviarAssinanteAluno'));

// Restos da sessão em que o XML foi capturado.
check('assinatura: HostName da máquina capturada removido', !str_contains($xml, 'NOTESUPER05'));
check('assinatura: Ip da máquina capturada removido', !str_contains($xml, '10.0.1.4'));
check('assinatura: usuário de rede capturado removido', !str_contains($xml, 'vladimir.bubans'));
checkSame('assinatura: NetworkUser = usuário de serviço', 'integra.eduvem', $texto($x, '/rm:*/t:NetworkUser'));
check('assinatura: ExecutionId capturado substituído', !str_contains($xml, '72515360-0944-4ca6-92da-75507883967d'));
check('assinatura: ScheduleDateTime capturado substituído', !str_contains($xml, '2023-10-30'));
check('assinatura: ScheduleDateTime = hoje', str_starts_with((string) $texto($x, '/rm:*/t:ScheduleDateTime'), date('Y-m-d')));
check('assinatura: ExecutionId é GUID', (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', (string) $texto($x, '/rm:*/t:ExecutionId')));
check('assinatura: ExecutionId novo a cada chamada', $texto($x, '/rm:*/t:ExecutionId') !== $texto($xp($montar()), '/rm:*/t:ExecutionId'));

/* ---------- blindagem ---------- */

$xmlNome = $montar(['nome' => 'Contrato <A> & Cia']);
check('assinatura: NomeDocumento com <>& gera XML bem-formado', xmlBemFormado($xmlNome));
checkSame('assinatura: NomeDocumento com <>& chega intacto ao RM', 'Contrato <A> & Cia', $texto($xp($xmlNome), '/rm:*/rm:NomeDocumento'));

$xmlChaves = $montar(['nome' => 'Contrato {{2026}}']);
checkSame('assinatura: NomeDocumento com "{{" é texto, não placeholder órfão', 'Contrato {{2026}}', $texto($xp($xmlChaves), '/rm:*/rm:NomeDocumento'));

$xmlRa = $montar(['ra' => 'x</a:anyType><X>1</X><a:anyType>x', 'contrato' => 'C</a:anyType><Y>1</Y><a:anyType>']);
check('assinatura: RA/CODCONTRATO maliciosos seguem bem-formados', xmlBemFormado($xmlRa));
check('assinatura: RA malicioso não injeta elemento', !str_contains($xmlRa, '<X>1</X>'));
check('assinatura: CODCONTRATO malicioso não injeta elemento', !str_contains($xmlRa, '<Y>1</Y>'));

$rejeita = static function (callable $fn): bool {
    try {
        $fn();
        return false;
    } catch (\InvalidArgumentException) {
        return true;
    }
};
check('assinatura: CODCOLIGADA "1e3" rejeitada', $rejeita(fn() => $montar(['col' => '1e3'])));
check('assinatura: IDPERLET com markup rejeitado', $rejeita(fn() => $montar(['perlet' => '7</X>'])));
check('assinatura: CODTIPOCURSO vazio rejeitado', $rejeita(fn() => $montar(['tipo' => ''])));
check('assinatura: IDREPORT texto rejeitado', $rejeita(fn() => $montar(['idrel' => 'abc'])));
check('assinatura: CODCOLIGADAREPORT negativo rejeitado', $rejeita(fn() => $montar(['colrel' => '-1'])));

/* =====================================================================
 * Service (RM e consultas trocados por dublês: nada sai da máquina)
 * ===================================================================== */

$rm = new class extends RMSoapClient {
    /** @var array<int,array{0:string,1:string}> */
    public array $chamadas = [];

    public function __construct()
    {
        parent::__construct('', '', '');
    }

    public function executeWithXmlParams(string $processServerName, string $xmlParams): string
    {
        $this->chamadas[] = [$processServerName, $xmlParams];
        return '98765';
    }
};

$consulta = new class($rm) extends ConsultaService {
    public ?array $ofertaRow = null;
    public ?array $plRow = null;
    public int $consultasPl = 0;

    public function oferta(string $codOferta): ?array
    {
        return $this->ofertaRow;
    }

    public function matriculaPeriodoLetivo(string $codOferta, string $ra): ?array
    {
        $this->consultasPl++;
        return $this->plRow;
    }

    public function logProcessoFormatado(string|int $jobId): ?string
    {
        return "LOG DO JOB {$jobId}";
    }
};

$config = [
    'usuario_servico' => 'integra.eduvem',
    'ws_url'          => 'https://rm.exemplo',
];

$reset = static function () use ($rm, $consulta): void {
    $rm->chamadas = [];
    $consulta->ofertaRow = ['CODCOLIGADA' => '1', 'CODFILIAL' => '3', 'IDPERLET' => '77', 'CODTIPOCURSO' => '2'];
    $consulta->plRow = ['CODCONTRATO' => 'CT-PL-9'];
    $consulta->consultasPl = 0;
};

$service = new AssinaturaService($rm, $consulta, $config);
$base = [
    'RA'                => '24001268',
    'OFERTA'            => 'OF2026-001',
    'NOMEDOCUMENTO'     => 'Contrato de Matrícula',
    'IDREPORT'          => '4321',
    'CODCOLIGADAREPORT' => '0',
];

/** Roda o service e devolve a ValidationException (ou null se não lançou). */
$falhaValidacao = static function (AssinaturaService $s, array $in): ?ValidationException {
    try {
        $s->enviar($in);
        return null;
    } catch (ValidationException $e) {
        return $e;
    }
};

// DRY_RUN: monta o XML completo e NÃO chama o processo.
$reset();
$r = $service->enviar($base + ['DRY_RUN' => true]);
check('service: DRY_RUN devolve dry_run=true', ($r['dry_run'] ?? null) === true);
checkSame('service: DRY_RUN não chama o RM', 0, count($rm->chamadas));
checkSame('service: DRY_RUN informa o processo', 'EduTotvsSignContratoSliceableProcData', $r['PROCESSO'] ?? null);
check('service: DRY_RUN devolve o XML', str_contains((string) ($r['xml'] ?? ''), '<EduTotvsSignSliceableParamsProc'));
checkSame('service: contrato resolvido pela matrícula no PL', 'CT-PL-9', $r['CODCONTRATO'] ?? null);
check('service: contrato resolvido vai para a PrimaryKeyList', str_contains((string) ($r['xml'] ?? ''), '>CT-PL-9</a:anyType>'));
check('service: valores da oferta no XML', str_contains((string) ($r['xml'] ?? ''), '<CodFilial>3</CodFilial>')
    && str_contains((string) ($r['xml'] ?? ''), '<CodTipoCurso>2</CodTipoCurso>'));
check('service: relatório do corpo no XML', str_contains((string) ($r['xml'] ?? ''), '<IdRelatorioRMReports>4321</IdRelatorioRMReports>')
    && str_contains((string) ($r['xml'] ?? ''), '<CodColigadaRelatorio>0</CodColigadaRelatorio>'));

// DRY_RUN em texto de formulário (n8n "Using Fields Below").
$reset();
$r = $service->enviar($base + ['DRY_RUN' => 'true']);
check('service: DRY_RUN "true" (form) também não chama o RM', ($r['dry_run'] ?? null) === true && count($rm->chamadas) === 0);

// CODCONTRATO no corpo: usado direto, sem consultar a matrícula.
$reset();
$r = $service->enviar($base + ['CODCONTRATO' => 'CT-CORPO', 'DRY_RUN' => true]);
checkSame('service: CODCONTRATO do corpo é usado', 'CT-CORPO', $r['CODCONTRATO'] ?? null);
checkSame('service: CODCONTRATO do corpo dispensa a consulta do PL', 0, $consulta->consultasPl);

// CODCOLIGADAREPORT 0 (coligada global) como número JSON não pode virar "vazio".
$reset();
$r = $service->enviar(['CODCOLIGADAREPORT' => 0, 'IDREPORT' => 4321, 'DRY_RUN' => true] + $base);
check('service: CODCOLIGADAREPORT 0 numérico é aceito', str_contains((string) ($r['xml'] ?? ''), '<CodColigadaRelatorio>0</CodColigadaRelatorio>'));

// Execução real: chama o processo certo, uma vez, com o XML montado.
$reset();
$r = $service->enviar($base);
checkSame('service: executa o processo uma vez', 1, count($rm->chamadas));
checkSame('service: ProcessServerName', 'EduTotvsSignContratoSliceableProcData', $rm->chamadas[0][0] ?? null);
check('service: XML enviado é o do builder', str_contains($rm->chamadas[0][1] ?? '', '>CT-PL-9</a:anyType>'));
checkSame('service: devolve o retorno do RM', '98765', $r['retorno_rm'] ?? null);
checkSame('service: anexa o log do job quando o RM devolve JobId', 'LOG DO JOB 98765', $r['log_job'] ?? null);

// Entradas inválidas: 422 (ValidationException) e o processo NÃO é disparado.
$casosInvalidos = [
    'sem RA'            => array_diff_key($base, ['RA' => 1]),
    'sem OFERTA'        => array_diff_key($base, ['OFERTA' => 1]),
    'sem NOMEDOCUMENTO' => array_diff_key($base, ['NOMEDOCUMENTO' => 1]),
    'sem IDREPORT'      => array_diff_key($base, ['IDREPORT' => 1]),
    'sem CODCOLIGADAREPORT' => array_diff_key($base, ['CODCOLIGADAREPORT' => 1]),
    'RA só espaços'     => ['RA' => '   '] + $base,
    'DRY_RUN "sim"'     => $base + ['DRY_RUN' => 'sim'],
    'IDREPORT texto'    => ['IDREPORT' => 'abc'] + $base,
];
foreach ($casosInvalidos as $nome => $in) {
    $reset();
    check("service: {$nome} → ValidationException", $falhaValidacao($service, $in) !== null);
    checkSame("service: {$nome} não dispara o processo", 0, count($rm->chamadas));
}

$reset();
$consulta->ofertaRow = null;
check('service: oferta inexistente → ValidationException', $falhaValidacao($service, $base) !== null);

$reset();
$consulta->plRow = null;
check('service: sem contrato localizável → ValidationException', $falhaValidacao($service, $base) !== null);

$reset();
$consulta->ofertaRow = ['CODCOLIGADA' => '1', 'CODFILIAL' => '3', 'IDPERLET' => '77'];
check('service: oferta sem CODTIPOCURSO → ValidationException (não 500)', $falhaValidacao($service, $base) !== null);
checkSame('service: oferta incompleta não dispara o processo', 0, count($rm->chamadas));

// O relatório vem SÓ do corpo: um valor antigo em config/env não pode ser
// usado em silêncio (mandaria o PDF de outro relatório ao aluno).
$reset();
$comConfigAntigo = new AssinaturaService($rm, $consulta, $config + [
    'assinatura' => ['relatorio_id' => '4321', 'relatorio_codcoligada' => '0'],
]);
$semRelatorio = array_diff_key($base, ['IDREPORT' => 1, 'CODCOLIGADAREPORT' => 1]);
check('service: relatório só vem do corpo (config é ignorado)', $falhaValidacao($comConfigAntigo, $semRelatorio) !== null);
checkSame('service: sem relatório no corpo não dispara o processo', 0, count($rm->chamadas));
