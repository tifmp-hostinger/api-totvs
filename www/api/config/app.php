<?php

declare(strict_types=1);

/**
 * Configuração geral da aplicação.
 * Agora compatível com Docker / EasyPanel (ENV vars).
 */

use FMP\RMApi\Support\Env;

return [
    'debug' => Env::get('APP_DEBUG', 'false') === 'true',

    'base' => Env::get('APP_BASE', ''),

    // Diretório gravável para o estado do painel de rotas (JSON).
    'var_dir' => Env::get('APP_VAR_DIR', dirname(__DIR__) . '/var'),

    'crypto' => [
        'key'    => Env::get('APP_CRYPTO_KEY', ''),
        'method' => Env::get('APP_CRYPTO_METHOD', 'aes-256-gcm'),
    ],
];