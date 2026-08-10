<?php

declare(strict_types=1);

/**
 * BaixaService::normalizarDecimal — conversão de valor monetário BR -> RM.
 * O método é privado (detalhe interno do service); testado via reflection
 * para não abrir a API pública só por causa do teste.
 */

use FMP\RMApi\Services\BaixaService;

$normalizar = new \ReflectionMethod(BaixaService::class, 'normalizarDecimal');

$casos = [
    '1.234,56' => '1234.56',   // BR completo (milhar + decimal)
    '465,00'   => '465.00',    // vírgula decimal
    '465.00'   => '465.00',    // já no formato RM
    '465'      => '465.00',    // inteiro ganha casas
    '1234.5'   => '1234.50',   // completa a segunda casa
    ''         => '0.00',      // vazio não explode
    // Formato US (o webhook da EDUNEXT usa ponto decimal): antes virava 1.23,
    // ou seja, uma baixa de R$ 1.234,56 era enviada como R$ 1,23.
    '1,234.56' => '1234.56',
    '10,000.00' => '10000.00',
    // Separador único seguido de 3 dígitos é milhar, não decimal:
    // "1.000" é mil reais, não um real.
    '1.000'    => '1000.00',
    '1,000'    => '1000.00',
    '10.000'   => '10000.00',
];

foreach ($casos as $entrada => $esperado) {
    checkSame("normalizarDecimal('{$entrada}')", $esperado, $normalizar->invoke(null, (string) $entrada));
}
