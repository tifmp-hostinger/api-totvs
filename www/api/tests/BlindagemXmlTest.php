<?php

declare(strict_types=1);

/**
 * Blindagem dos valores que entram no XML enviado ao RM.
 *
 * Antes destas checagens, um valor de requisição (RA, DATABAIXA, IDFORMAPAGTO)
 * era interpolado cru no envelope: dava para injetar elementos no payload de um
 * processo executado no ERP, e um simples "&" num campo legítimo quebrava a
 * desserialização .NET com erro opaco.
 */

use FMP\RMApi\Services\PessoaService;
use FMP\RMApi\Support\ProcessXml;

$rejeita = static function (callable $fn): bool {
    try {
        $fn();
        return false;
    } catch (\InvalidArgumentException) {
        return true;
    } catch (\Throwable) {
        return false;
    }
};

$pl = static fn(string $ra) => ProcessXml::matriculaPeriodoLetivo(
    codColigada: 1, codFilial: 1, idHabilitacaoFilial: 42, idPerlet: 7,
    ra: $ra, codTurma: 'TURMA-X', codPlanoPagamento: 'IMER10X',
    now: '2026-08-10T10:00:00'
);

$baixa = static fn(array $o = []) => ProcessXml::baixaLancamentoTbc(
    codColigada: $o['col'] ?? 1,
    codFilial: 1,
    idLan: $o['idlan'] ?? '1091335',
    valorBaixa: '500.00',
    codCxa: '50380',
    dataBaixa: $o['data'] ?? '2026-07-17',
    historico: $o['hist'] ?? 'Baixa via API',
    idFormaPagto: $o['forma'] ?? '1'
);

/* ---------- injeção de markup ---------- */

$xml = $pl('x</RA><CodStatusNovo>99</CodStatusNovo><RA>x');
check('RA malicioso não injeta elemento no envelope', !str_contains($xml, '<CodStatusNovo>99</CodStatusNovo>'));
check('RA malicioso continua bem-formado', xmlBemFormado($xml));

check('RA com & gera XML bem-formado', xmlBemFormado($pl('24001&268')));
check('histórico com & e <> gera XML bem-formado', xmlBemFormado($baixa(['hist' => 'Baixa & cia <teste>'])));

check('contrato com markup não injeta', !str_contains(
    ProcessXml::gerarLancamento(codColigada: 1, codFilial: 1, idPerlet: 7, ra: '24001268', codContrato: 'C</CodContrato><X>1'),
    '<X>1</X>'
));

/* ---------- validação de tipo (o RM desserializa como int/dateTime) ---------- */

check('DATABAIXA com markup é rejeitada', $rejeita(fn() => $baixa(['data' => '2026-08-10</DataBaixa><Foo>x'])));
check('DATABAIXA em formato BR é rejeitada no builder', $rejeita(fn() => $baixa(['data' => '10/08/2026'])));
check('DATABAIXA inexistente (2026-02-31) é rejeitada', $rejeita(fn() => $baixa(['data' => '2026-02-31'])));
check('IDLAN "1e3" é rejeitado (is_numeric aceitava)', $rejeita(fn() => $baixa(['idlan' => '1e3'])));
check('IDFORMAPAGTO com markup é rejeitado', $rejeita(fn() => $baixa(['forma' => '1</X><Y>2'])));
check('entrada legítima continua passando', xmlBemFormado($baixa()));

/* ---------- pessoa: atualização parcial não pode apagar campos ---------- */

$update = PessoaService::buildXml(['CODIGO' => '12345', 'NOME' => 'Fulano de Tal']);
check('update não emite DTNASCIMENTO vazia (evita apagar a data no RM)', !str_contains($update, '<DTNASCIMENTO>'));
check('update não emite CPF vazio', !str_contains($update, '<CPF>'));
check('update mantém o campo informado', str_contains($update, '<NOME>Fulano de Tal</NOME>'));
check('update é bem-formado', xmlBemFormado($update));

$create = PessoaService::buildXml(['CODIGO' => 0, 'NOME' => 'Fulano de Tal']);
check('criação continua emitindo todas as tags (contrato do RM)', str_contains($create, '<DTNASCIMENTO></DTNASCIMENTO>'));
check('criação é bem-formada', xmlBemFormado($create));
