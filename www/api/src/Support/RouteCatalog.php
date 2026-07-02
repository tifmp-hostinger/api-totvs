<?php

declare(strict_types=1);

namespace FMP\RMApi\Support;

/**
 * Catálogo canônico das rotas da API.
 *
 * É a fonte única de verdade usada pelo painel administrativo (/admin.html):
 *  - o RouteGate identifica a rota atendida por "METHOD pattern" e decide
 *    se ela está ativa;
 *  - o AdminRotasController devolve este catálogo (com estado + estatísticas)
 *    para a interface montar a listagem e o testador de requisições.
 *
 * Ao criar uma rota nova em config/routes.php, registre-a aqui também —
 * rotas fora do catálogo continuam funcionando, mas não aparecem no painel
 * nem podem ser desativadas.
 *
 * Campos de cada entrada:
 *  - id         identificador estável (usado na persistência e na URL do admin)
 *  - metodo     GET/POST/PATCH...
 *  - padrao     pattern Slim (com {placeholders})
 *  - grupo      agrupamento exibido no painel
 *  - descricao  texto curto para o painel
 *  - protegida  true = não pode ser desativada (health check e o próprio admin)
 *  - exemplo    valores de exemplo p/ o testador: params, query, body
 */
final class RouteCatalog
{
    /** @var array<string, array<string, mixed>>|null cache indexado por id */
    private static ?array $porId = null;

    /** @var array<string, string>|null cache "METHOD padrao" => id */
    private static ?array $porPadrao = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function todas(): array
    {
        return [
            /* ---------- Sistema ---------- */
            [
                'id' => 'status.consultar', 'metodo' => 'GET', 'padrao' => '/status',
                'grupo' => 'Sistema', 'protegida' => true,
                'descricao' => 'Health check do RM (INT.EDUVEM.00001). Isento de API key e não pode ser desativado.',
            ],

            /* ---------- RM genérico (diagnóstico) ---------- */
            [
                'id' => 'rm.test', 'metodo' => 'GET', 'padrao' => '/rm/test',
                'grupo' => 'RM genérico (diagnóstico)',
                'descricao' => 'Valida conectividade e credenciais SOAP com o RM.',
            ],
            [
                'id' => 'rm.schema', 'metodo' => 'GET', 'padrao' => '/rm/schema/{dataserver}',
                'grupo' => 'RM genérico (diagnóstico)',
                'descricao' => 'Schema parseado do DataServer (tabelas, campos, chaves). ?xml=1 devolve o XSD bruto.',
                'exemplo' => [
                    'params' => ['dataserver' => 'RhuPessoaData'],
                    'query'  => [['name' => 'xml', 'val' => '', 'label' => 'xml (1 = XSD bruto)']],
                ],
            ],
            [
                'id' => 'rm.sql', 'metodo' => 'POST', 'padrao' => '/rm/sql/{codsentenca}',
                'grupo' => 'RM genérico (diagnóstico)',
                'descricao' => 'Executa uma sentença SQL cadastrada no RM.',
                'exemplo' => [
                    'params' => ['codsentenca' => 'INT.EDUVEM.00007'],
                    'body'   => ['parametros' => ['CPF_S' => '12345678901', 'RNM_S' => '0'], 'codcoligada' => '0', 'codsistema' => 'G'],
                ],
            ],
            [
                'id' => 'rm.read', 'metodo' => 'POST', 'padrao' => '/rm/read/{dataserver}',
                'grupo' => 'RM genérico (diagnóstico)',
                'descricao' => 'ReadRecord: lê um registro pela chave primária.',
                'exemplo' => [
                    'params' => ['dataserver' => 'RhuPessoaData'],
                    'body'   => ['chave' => ['12345'], 'contexto' => (object) []],
                ],
            ],
            [
                'id' => 'rm.view', 'metodo' => 'POST', 'padrao' => '/rm/view/{dataserver}',
                'grupo' => 'RM genérico (diagnóstico)',
                'descricao' => 'ReadView: consulta com filtro SQL na view do DataServer.',
                'exemplo' => [
                    'params' => ['dataserver' => 'GlbColigadaData'],
                    'body'   => ['filtro' => 'CODCOLIGADA=1', 'contexto' => (object) []],
                ],
            ],
            [
                'id' => 'rm.save', 'metodo' => 'POST', 'padrao' => '/rm/save/{dataserver}',
                'grupo' => 'RM genérico (diagnóstico)',
                'descricao' => 'SaveRecord genérico (uso avançado/diagnóstico). Grava dados reais no RM.',
                'exemplo' => [
                    'params' => ['dataserver' => 'RhuPessoaData'],
                    'body'   => [
                        'xml'      => '<RhuPessoa><PPessoa><CODIGO>0</CODIGO><NOME>Teste Da Silva</NOME><CPF>12345678901</CPF></PPessoa><VPCompl><CODPESSOA>0</CODPESSOA></VPCompl></RhuPessoa>',
                        'contexto' => ['CODCOLIGADA' => '1', 'CODSISTEMA' => 'S', 'CODUSUARIO' => 'integra.eduvem'],
                    ],
                ],
            ],

            /* ---------- Pessoa ---------- */
            [
                'id' => 'pessoas.salvar', 'metodo' => 'POST', 'padrao' => '/pessoas',
                'grupo' => 'Pessoa',
                'descricao' => 'Cria (CODIGO=0/ausente) ou atualiza (CODIGO>0) a PPessoa. Retorna CODPESSOA.',
                'exemplo' => [
                    'body' => [
                        'CODIGO' => 0, 'NOME' => 'Fulano de Tal', 'DTNASCIMENTO' => '1990-01-15', 'SEXO' => 'M',
                        'CPF' => '123.456.789-01', 'CEP' => '01310-100', 'TELEFONE1' => '11987654321', 'EMAIL' => 'fulano@email.com',
                    ],
                ],
            ],
            [
                'id' => 'pessoas.busca', 'metodo' => 'GET', 'padrao' => '/pessoas/busca',
                'grupo' => 'Pessoa',
                'descricao' => 'Localiza a PPessoa por CPF ou RNM (preencha um).',
                'exemplo' => [
                    'query' => [
                        ['name' => 'cpf', 'val' => '12345678901', 'label' => 'cpf'],
                        ['name' => 'rnm', 'val' => '', 'label' => 'rnm'],
                    ],
                ],
            ],
            [
                'id' => 'pessoas.buscar', 'metodo' => 'GET', 'padrao' => '/pessoas/{codigo}',
                'grupo' => 'Pessoa',
                'descricao' => 'ReadRecord RhuPessoaData pelo código da pessoa.',
                'exemplo' => ['params' => ['codigo' => '12345']],
            ],

            /* ---------- Aluno ---------- */
            [
                'id' => 'alunos.salvar', 'metodo' => 'POST', 'padrao' => '/alunos',
                'grupo' => 'Aluno',
                'descricao' => 'Cria/atualiza o aluno com etapas rastreadas: CLIENTE/FORNECEDOR → ALUNO → USUÁRIO/FILIAL → ACESSO (SSO).',
                'exemplo' => [
                    'body' => ['CODPESSOA' => 12345, 'CODCOLIGADA' => 1, 'CODTIPOCURSO' => 2, 'CODFILIAL' => 1, 'CPF' => '12345678901', 'RNM' => ''],
                ],
            ],
            [
                'id' => 'alunos.vincular-cfo', 'metodo' => 'POST', 'padrao' => '/alunos/cliente-fornecedor',
                'grupo' => 'Aluno',
                'descricao' => 'Vincula um Cliente/Fornecedor já gravado a um aluno existente (por RA).',
                'exemplo' => [
                    'body' => ['RA' => '000123', 'CODCOLIGADA' => 1, 'CODTIPOCURSO' => 2, 'CODFILIAL' => 1, 'CODCOLCFO' => 0, 'CODCFO' => ''],
                ],
            ],
            [
                'id' => 'alunos.buscar', 'metodo' => 'GET', 'padrao' => '/alunos/{codcoligada}/{codpessoa}',
                'grupo' => 'Aluno',
                'descricao' => 'Retorna RA, CODUSUARIO, SENHAPADRAO, EXISTESUSUARIOFILIAL, último acesso.',
                'exemplo' => ['params' => ['codcoligada' => '1', 'codpessoa' => '12345']],
            ],

            /* ---------- Cliente/Fornecedor ---------- */
            [
                'id' => 'cfo.busca', 'metodo' => 'GET', 'padrao' => '/clientes-fornecedores/busca',
                'grupo' => 'Cliente/Fornecedor',
                'descricao' => 'Consulta o CFO por CPF/RNM antes de criar (INT.EDUVEM.00009). 404 = não existe.',
                'exemplo' => [
                    'query' => [
                        ['name' => 'cpf', 'val' => '12345678901', 'label' => 'cpf'],
                        ['name' => 'rnm', 'val' => '', 'label' => 'rnm'],
                    ],
                ],
            ],
            [
                'id' => 'cfo.salvar', 'metodo' => 'POST', 'padrao' => '/clientes-fornecedores',
                'grupo' => 'Cliente/Fornecedor',
                'descricao' => 'Cria o CFO com etapas (VALIDAÇÃO → CONSULTA → GRAVAÇÃO). Idempotente pelo documento.',
                'exemplo' => [
                    'body' => [
                        'NOME' => 'Felipe Machado da Silva', 'CGCCFO' => '517.420.330-08', 'RUA' => 'Av. Ipiranga',
                        'NUMERO' => '6681', 'BAIRRO' => 'Partenon', 'CIDADE' => 'Porto Alegre', 'CODETD' => 'RS',
                        'CEP' => '90619-900', 'TELEFONE' => '(51) 3333-6565', 'EMAIL' => 'felipe@exemplo.com',
                    ],
                ],
            ],

            /* ---------- Inscrição ---------- */
            [
                'id' => 'inscricoes.criar', 'metodo' => 'POST', 'padrao' => '/inscricoes',
                'grupo' => 'Inscrição (fluxo completo)',
                'descricao' => 'Fluxo orquestrado: pessoa → aluno → matrículas → enturmação → cupom → lançamento. Idempotente.',
                'exemplo' => [
                    'body' => [
                        'OFERTA' => 'OF2026-001', 'PLANOPAGAMENTO' => 'PP01', 'CPF' => '12345678901',
                        'NOME' => 'Fulano de Tal', 'NASCIMENTO' => '1990-01-15', 'SEXO' => 'M',
                        'EMAIL' => 'fulano@email.com', 'TELEFONE' => '11987654321', 'CEP' => '01310100',
                        'ESTADO' => 'SP', 'CIDADE' => '3550308', 'BAIRRO' => '123', 'RUA' => 'Av. Paulista',
                        'NUMERO' => '1000', 'COMPLEMENTO' => '', 'NATURALIDADE' => '3550308', 'CUPOM' => 'PROMO10',
                    ],
                ],
            ],

            /* ---------- Matrícula ---------- */
            [
                'id' => 'matriculas.curso', 'metodo' => 'POST', 'padrao' => '/matriculas/curso',
                'grupo' => 'Matrícula (etapas)',
                'descricao' => 'Pré-matrícula no curso (CODSTATUS 23) via EduHabilitacaoAlunoData.',
                'exemplo' => ['body' => ['RA' => '000123', 'OFERTA' => 'OF2026-001']],
            ],
            [
                'id' => 'matriculas.periodo-letivo', 'metodo' => 'POST', 'padrao' => '/matriculas/periodo-letivo',
                'grupo' => 'Matrícula (etapas)',
                'descricao' => "Processo 'Matricular aluno' — gera o contrato. Retorna CODCONTRATO.",
                'exemplo' => ['body' => ['RA' => '000123', 'OFERTA' => 'OF2026-001', 'PLANOPAGAMENTO' => 'PP01']],
            ],
            [
                'id' => 'matriculas.disciplinas', 'metodo' => 'POST', 'padrao' => '/matriculas/disciplinas',
                'grupo' => 'Matrícula (etapas)',
                'descricao' => "Processo 'Matricular aluno nas disciplinas' por turma da oferta.",
                'exemplo' => ['body' => ['RA' => '000123', 'OFERTA' => 'OF2026-001']],
            ],

            /* ---------- Contrato ---------- */
            [
                'id' => 'contratos.gerar', 'metodo' => 'POST', 'padrao' => '/contratos',
                'grupo' => 'Contrato',
                'descricao' => 'Gera o PDF do contrato (relatório 1664): GenerateReport → Size → FileChunk.',
                'exemplo' => [
                    'body' => [
                        'NOME' => 'Fulano de Tal', 'CPF' => '12345678901', 'ESTADO' => 'SP', 'CIDADE' => '3550308',
                        'BAIRRO' => '123', 'RUA' => 'Av. Paulista', 'NUMERO' => '1000', 'COMPLEMENTO' => '',
                        'NACIONALIDADE' => 'Brasileira', 'NASCIMENTO' => '1990-01-15',
                    ],
                ],
            ],

            /* ---------- Oferta ---------- */
            [
                'id' => 'ofertas.buscar', 'metodo' => 'GET', 'padrao' => '/ofertas/{codoferta}',
                'grupo' => 'Oferta',
                'descricao' => 'Dados da oferta (INT.EDUVEM.00006).',
                'exemplo' => ['params' => ['codoferta' => 'OF2026-001']],
            ],
            [
                'id' => 'ofertas.planos', 'metodo' => 'GET', 'padrao' => '/ofertas/{codoferta}/planos-pagamento',
                'grupo' => 'Oferta',
                'descricao' => 'Planos de pagamento da oferta (INT.EDUVEM.00013).',
                'exemplo' => ['params' => ['codoferta' => 'OF2026-001']],
            ],

            /* ---------- Endereço ---------- */
            [
                'id' => 'enderecos.estados', 'metodo' => 'GET', 'padrao' => '/enderecos/estados',
                'grupo' => 'Endereço',
                'descricao' => 'Lista de estados (INT.EDUVEM.00002).',
            ],
            [
                'id' => 'enderecos.cidades', 'metodo' => 'GET', 'padrao' => '/enderecos/estados/{codestado}/cidades',
                'grupo' => 'Endereço',
                'descricao' => 'Cidades do estado (INT.EDUVEM.00003).',
                'exemplo' => ['params' => ['codestado' => 'SP']],
            ],
            [
                'id' => 'enderecos.bairros', 'metodo' => 'GET', 'padrao' => '/enderecos/cidades/{codcidade}/bairros',
                'grupo' => 'Endereço',
                'descricao' => 'Bairros da cidade (INT.EDUVEM.00004).',
                'exemplo' => ['params' => ['codcidade' => '3550308']],
            ],
            [
                'id' => 'enderecos.cep', 'metodo' => 'GET', 'padrao' => '/enderecos/cep/{cep}',
                'grupo' => 'Endereço',
                'descricao' => 'Endereço por CEP (INT.EDUVEM.00005).',
                'exemplo' => ['params' => ['cep' => '01310100']],
            ],

            /* ---------- Cupom ---------- */
            [
                'id' => 'cupons.buscar', 'metodo' => 'GET', 'padrao' => '/cupons/{codoferta}/{codplano}/{cupom}',
                'grupo' => 'Cupom',
                'descricao' => 'Valida cupom para oferta + plano (INT.EDUVEM.00016).',
                'exemplo' => ['params' => ['codoferta' => 'OF2026-001', 'codplano' => 'PP01', 'cupom' => 'PROMO10']],
            ],

            /* ---------- SSO ---------- */
            [
                'id' => 'sso.signin', 'metodo' => 'GET', 'padrao' => '/sso/{token}',
                'grupo' => 'SSO (exceção HTML)',
                'descricao' => 'Auto-login no Portal Educacional (única rota HTML). Isenta de API key.',
                'exemplo' => ['params' => ['token' => 'TOKEN_GERADO_PELA_INSCRICAO']],
            ],

            /* ---------- Administração (este painel) ---------- */
            [
                'id' => 'admin.rotas.listar', 'metodo' => 'GET', 'padrao' => '/admin/rotas',
                'grupo' => 'Administração', 'protegida' => true,
                'descricao' => 'Lista o catálogo de rotas com estado (ativa/desativada) e estatísticas de uso.',
            ],
            [
                'id' => 'admin.rotas.alterar', 'metodo' => 'PATCH', 'padrao' => '/admin/rotas/{id}',
                'grupo' => 'Administração', 'protegida' => true,
                'descricao' => 'Ativa/desativa uma rota. Body: { "ativa": true|false, "motivo": "..." }.',
                'exemplo' => ['params' => ['id' => 'rm.save'], 'body' => ['ativa' => false, 'motivo' => 'manutenção']],
            ],
            [
                'id' => 'admin.rotas.lote', 'metodo' => 'POST', 'padrao' => '/admin/rotas/lote',
                'grupo' => 'Administração', 'protegida' => true,
                'descricao' => 'Ativa/desativa várias rotas de uma vez. Body: { "ativa": bool, "rotas": ["id1","id2"], "motivo": "..." }.',
                'exemplo' => ['body' => ['ativa' => false, 'rotas' => ['rm.save', 'rm.sql'], 'motivo' => 'manutenção']],
            ],
            [
                'id' => 'admin.rotas.zerar-estatisticas', 'metodo' => 'POST', 'padrao' => '/admin/rotas/estatisticas/zerar',
                'grupo' => 'Administração', 'protegida' => true,
                'descricao' => 'Zera os contadores de uso de todas as rotas.',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>> indexado por id
     */
    public static function porId(): array
    {
        if (self::$porId === null) {
            self::$porId = [];
            foreach (self::todas() as $rota) {
                self::$porId[$rota['id']] = $rota + ['protegida' => false];
            }
        }
        return self::$porId;
    }

    public static function existe(string $id): bool
    {
        return isset(self::porId()[$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function buscar(string $id): ?array
    {
        return self::porId()[$id] ?? null;
    }

    /**
     * Resolve o id da rota a partir do método HTTP + pattern Slim
     * (ex.: "POST /rm/sql/{codsentenca}"). Null = rota fora do catálogo.
     */
    public static function idPorPadrao(string $metodo, string $padrao): ?string
    {
        if (self::$porPadrao === null) {
            self::$porPadrao = [];
            foreach (self::porId() as $id => $rota) {
                self::$porPadrao[strtoupper($rota['metodo']) . ' ' . $rota['padrao']] = $id;
            }
        }
        return self::$porPadrao[strtoupper($metodo) . ' ' . $padrao] ?? null;
    }
}
