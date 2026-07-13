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

    // Baixa de lançamento. Nome do process server e operação SOAP configuráveis
    // por env — o RM expõe a baixa sob um nome que varia por versão/patch.
    //   FIN_BAIXA_PROCESSO: ProcessServerName   FIN_BAIXA_OPERACAO: ExecuteWithParams | ExecuteWithXMLParams
    //   FIN_CODCXA_PADRAO : conta/caixa default quando não vier no corpo
    //
    // ATENÇÃO: "Classe não encontrada: FinLanBaixaProc" = o nome NÃO existe
    // nesta instância. Descubra o nome real por inspeção READ-ONLY no RM:
    //   (a) Monitor de Jobs de uma baixa já feita → coluna "Classe de Processo"; ou
    //   (b) tela "Baixar" → "Salvar parâmetros como XML" e CANCELE antes de confirmar
    //       (revela o ProcessServerName E o elemento-raiz correto do XML).
    // Candidatos (convenção local + pesquisa; NÃO confirmados nesta instância):
    //   1) FinLanBaixaProcData    — reaproveita o XML atual <FinLanBaixaParamsProc> (só troca env)
    //   2) FinLanBaixaTBCData      — idem (pode ser o DataServer, não o processo)
    //   3) FinTBCBaixaDataProcess  — EXIGE outro XML (<FinTBCBaixaParamsProc>); não é só renomear
    // Operação recomendada: ExecuteWithXMLParams (os processos que funcionam usam-na
    // — MatriculaService/LancamentoService — e força separador decimal '.').
    // NUNCA teste nomes em produção: nome errado é inofensivo, mas o nome CERTO
    // EXECUTA uma baixa real. Confirme antes; teste em homologação/sandbox.
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