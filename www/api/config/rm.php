<?php

declare(strict_types=1);

/**
 * Configuração da conexão SOAP com o TOTVS RM
 * versão compatível com Docker / EasyPanel (ENV vars).
 */

use FMP\RMApi\Support\Env;

return [
    // Conexão SOAP
    'ws_url'      => Env::get('TOTVS_WS_URL', ''),
    'ws_user'     => Env::get('TOTVS_WS_USER', ''),
    'ws_password' => Env::get('TOTVS_WS_PASSWORD', ''),
    // Contexto padrão
    'contexto_padrao' => [
        'CODSISTEMA' => 'S',
        'CODUSUARIO' => Env::get('TOTVS_WS_USER', 'integra.eduvem'),
    ],
    // SQL padrões
    'sql' => [
        'codcoligada' => '0',
        'codsistema'  => 'G',
    ],

    // Usuário de serviço
    'usuario_servico' => Env::get('TOTVS_WS_USER', 'integra.eduvem'),

    // Baixa de lançamento (FinLanBaixaProc). Nome do process server e operação
    // SOAP configuráveis por env — o RM pode expor a baixa sob outro nome/
    // operação conforme a versão. Ajuste sem redeploy de código.
    //   FIN_BAIXA_PROCESSO: ProcessServerName (default abaixo)
    //   FIN_BAIXA_OPERACAO: ExecuteWithParams (default) | ExecuteWithXMLParams
    //   FIN_CODCXA_PADRAO : conta/caixa default quando não vier no corpo
    'baixa' => [
        'processo' => Env::get('FIN_BAIXA_PROCESSO', 'FinLanBaixaProc'),
        'operacao' => Env::get('FIN_BAIXA_OPERACAO', 'ExecuteWithParams'),
    ],

    // Relatório contrato
    'relatorio_contrato' => [
        'codcoligada' => '0',
        'id'          => '1664',
    ],

    // Portal (mantido fixo pois não depende de env)
    'portal' => [
        'login_url'     => 'https://fundacaoescola114384.rm.cloudtotvs.com.br/FrameHTML/Web/App/Edu/PortalEducacional/login/',
        'autologin_url' => 'https://fundacaoescola114384.rm.cloudtotvs.com.br/Corpore.Net/Source/EDU-EDUCACIONAL/Public/EduPortalAlunoLogin.aspx?AutoLoginType=ExternalLogin&redirect=financeiro.new',
        'alias'         => 'CorporeRM',
    ],
];