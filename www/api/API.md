# FMP RM-API — Documentação Técnica

API REST de integração com o **TOTVS RM via SOAP**. Sem frontend, sem checkout, sem renderização HTML (exceção única e documentada: `/sso`).

> **Exemplos prontos:** `docs/CURLS.md` (body + cURL de cada endpoint, colável no *Import cURL* do n8n) e `docs/postman_collection.json` (coleção Postman/Insomnia com variável `baseUrl`).
>
> **Página interativa:** `public/docs.html` — servida em `https://SEU-SERVIDOR/docs.html`. Lista todos os endpoints com body editável e gera o cURL pronto pra copiar.
>
> **Deploy / EasyPanel / variáveis de ambiente / troubleshooting:** `docs/DEPLOY-EASYPANEL.md`.
>
> **Base path:** a app é servida na **raiz** (`setBasePath('')`). As rotas são `/pessoas`, `/rm/test` etc., **sem** `/api`.
>
> **Autenticação:** quando a env `API_KEY` está definida, toda requisição precisa do header `X-API-Key: <chave>` (ou `Authorization: Bearer <chave>`). Isentas: `GET /status` e `GET /sso/{token}`. Sem chave válida → **401**. Detalhes em `docs/DEPLOY-EASYPANEL.md`.
>
> **Formato do corpo (POST):** a API aceita **`application/json`** e **`application/x-www-form-urlencoded`** (campos soltos). No n8n, tanto "Using JSON" quanto "Using Fields Below" funcionam. Corpos aninhados (ex.: `/rm/sql`, `/rm/read`) → use JSON.

```
www/api/
├── .env / .env.example
├── composer.json                  (namespace FMP\RMApi)
├── config/
│   ├── rm.php                     ← conexão SOAP + contextos padrão + portal
│   ├── app.php                    ← debug, base path, crypto SSO
│   ├── dependencies.php           ← container PHP-DI
│   └── routes.php
├── public/index.php               ← bootstrap + error handler central
└── src/
    ├── Clients/
    │   ├── RMSoapClient.php       ← ÚNICO ponto de contato SOAP
    │   └── RMWSType.php           ← enum dos endpoints WSDL
    ├── Services/                  ← regra de negócio (1 domínio por classe)
    ├── Controllers/               ← só HTTP (entrada/saída JSON)
    ├── Exceptions/                ← RMException, FluxoException, ValidationException
    ├── Support/                   ← ProcessXml, ReportXml, SchemaParser
    └── Helpers/                   ← Json, Validation, Crypto, Format
```

**Fluxo de dependência:** `Controller → Service → RMSoapClient → TOTVS RM`. Controllers nunca tocam SOAP; services nunca montam Response.

---

## 1. Envelope de resposta

**Sucesso (200/201):**
```json
{ "sucesso": true, "mensagem": "...", "dados": { } }
```

**Erro de validação (422) / etapa de fluxo (422):**
```json
{
  "sucesso": false,
  "mensagem": "O CPF informado (123) não parece correto...",
  "etapa": "PESSOA",
  "detalhe": "CPF inválido informado",
  "operacao": "SaveRecord",
  "dataserver": "RhuPessoaData",
  "retorno_rm": "Número de CPF inválido! ..."
}
```

**Erro do RM (502):**
```json
{
  "sucesso": false,
  "mensagem": "...",
  "operacao": "SaveRecord",
  "dataserver": "RhuPessoaData",
  "retorno_rm": "Column 'NOME' does not allow nulls."
}
```

**Rota inexistente:** 404. **Erro interno:** 500.

### Modo debug (`APP_DEBUG=true`)
Defina como **variável de ambiente** no EasyPanel (ou no `.env` — ver `docs/DEPLOY-EASYPANEL.md`). Todo erro de RM passa a incluir:
```json
"debug": {
  "contexto":      { "CODCOLIGADA": "1", "CODSISTEMA": "S", "CODUSUARIO": "integra.eduvem" },
  "xml_enviado":   "<RhuPessoa>...</RhuPessoa>",
  "xml_retornado": "<s:Envelope>...</s:Envelope>",
  "soap_fault":    { "faultcode": "...", "faultstring": "...", "detail": {} }
}
```
O `SoapClient` roda com `trace=true`; request/response brutos são sempre capturados.

---

## 2. Rotas

### Sistema / RM genérico (diagnóstico)

| Rota | Descrição |
|---|---|
| `GET /status` | Health check (sentença INT.EDUVEM.00001) |
| `GET /rm/test` | Testa conexão/credenciais com o RM |
| `GET /rm/schema/{dataserver}` | Schema parseado (tabelas, campos, chaves, FKs). `?xml=1` devolve o XSD bruto (alias antigo: `?raw=1`) |
| `POST /rm/sql/{codsentenca}` | Executa sentença SQL cadastrada. Body: `{ "parametros": {"CPF_S": "..."}, "codcoligada": "0", "codsistema": "G" }` |
| `POST /rm/read/{dataserver}` | ReadRecord. Body: `{ "chave": ["123"], "contexto": {} }` |
| `POST /rm/view/{dataserver}` | ReadView. Body: `{ "filtro": "CODCOLIGADA=1", "contexto": {} }` |
| `POST /rm/save/{dataserver}` | SaveRecord genérico. Body: `{ "xml": "<...>", "contexto": {} }` |

### Pessoa (RhuPessoaData)

| Rota | Body | Retorno |
|---|---|---|
| `POST /pessoas` | Campos da PPessoa: `CODIGO` (0/ausente = criar), `NOME`, `DTNASCIMENTO`, `SEXO`, `CPF`, `EMAIL`, `TELEFONE1`, `RUA`, `NUMERO`, `BAIRRO`, `ESTADO`, `CIDADE`, `CEP`, `CODMUNICIPIO`, `IDPAIS`, `NROREGGERAL`... (CPF/CEP/TELEFONE1 são normalizados p/ dígitos) | 201 + `{ "CODPESSOA": "12345" }` |
| `GET /pessoas/{codigo}` | — | dados completos da PPessoa |
| `GET /pessoas/busca?cpf=...` (ou `?rnm=...`) | — | PPessoa ou 404. Aceita `cpf`/`CPF` em qualquer caixa |

XML enviado ao RM (SaveRecord `RhuPessoaData`, sem contexto):
```xml
<RhuPessoa>
    <PPessoa>
        <CODIGO>0</CODIGO>
        <NOME>Fulano de Tal</NOME>
        <DTNASCIMENTO>1990-01-15</DTNASCIMENTO>
        <SEXO>M</SEXO>
        <NACIONALIDADE>10</NACIONALIDADE>
        <CPF>12345678901</CPF>
        <EMAIL>fulano@email.com</EMAIL>
        <!-- demais campos -->
    </PPessoa>
    <VPCompl><CODPESSOA>0</CODPESSOA></VPCompl>
</RhuPessoa>
```
Retorno do RM = `CODPESSOA` (numérico) ou mensagem de validação (exposta em `retorno_rm`).

### Aluno (EduAlunoData / EduUsuarioFilialData / GlbUsuarioData)

| Rota | Body | Retorno |
|---|---|---|
| `POST /alunos` | `{ "CODPESSOA": 123, "CODCOLIGADA": 1, "CODTIPOCURSO": 2, "CODFILIAL": 1, "CPF": "...", "RNM": "" }` | 201 + `{ chave, autoLogin, nextUrl, etapas }` |
| `POST /alunos/cliente-fornecedor` | `{ "RA": "...", "CODCOLIGADA": 1, "CODCOLCFO": 0, "CODCFO": "..." }` | 200 + `{ chave, etapas }` |
| `GET /alunos/{codcoligada}/{codpessoa}` | — | RA, CODUSUARIO, SENHAPADRAO, EXISTESUSUARIOFILIAL, DATAULTIMOACESSOVALIDO |

**`POST /alunos` agora é orquestrado com rastreamento de etapas** (como a inscrição):
`CLIENTE/FORNECEDOR` (valida o cliFor pelo CPF/RNM via `INT.EDUVEM.00009`) → `ALUNO` (EduAlunoData) → `USUÁRIO/FILIAL` (EduUsuarioFilialData) → `ACESSO` (GlbUsuarioData + SSO). Sucesso devolve `dados.etapas`; erro de RM lança `FluxoException` (422) com `etapa` + `etapas_concluidas`.

`POST /alunos/cliente-fornecedor` faz uma gravação **direta** no EduAlunoData só com `CODCOLIGADA` (do aluno), `RA`, `CODCOLCFO` e `CODCFO` — não roda o resto do fluxo. `CODTIPOCURSO`/`CODFILIAL` são opcionais (só entram no contexto se enviados).

Contexto do SaveRecord educacional:
```
CODCOLIGADA={n};CODTIPOCURSO={n};CODFILIAL={n};CODSISTEMA=S;CODUSUARIO=integra.eduvem
```

### Cliente/Fornecedor (FinCFODataBR)

| Rota | Body / Query | Retorno |
|---|---|---|
| `GET /clientes-fornecedores/busca?cpf=...` (ou `?rnm=...`) | — | CFO (CODCOLCFO, CODCFO...) ou 404. Reusa `INT.EDUVEM.00009` |
| `POST /clientes-fornecedores` | `NOME` (obrig.), `CGCCFO` (CPF/CNPJ), `RUA`, `NUMERO`, `BAIRRO`, `CIDADE`, `CODETD`, `CEP`, `TELEFONE`, `EMAIL`... | 201 + `{ "CHAVE": "0;{CODCFO}" }` |

Regras fixas na criação: **CODCOLIGADA do registro = 0** (CFO global, vai no XML), **CODCFO=0** (o RM gera o código), **PAGREC=3**, **PESSOAFISOUJUR** derivado do documento (11 díg.=`F`, 14=`J`). **Contexto do SaveRecord: `CODCOLIGADA=1;CODSISTEMA=F;CODUSUARIO=integra.eduvem`** (a coligada 0 não permite CFO global). CGCCFO/CEP/TELEFONE são normalizados p/ dígitos. Envia `<FCFO>` + `<FCFOCOMPL>` (chaves); não envia `<FCFOMX>`.

`POST /clientes-fornecedores` é **rastreado por etapas** e **idempotente**: `VALIDAÇÃO` → `CONSULTA` (se o documento já existir, devolve `JA_EXISTIA` sem duplicar) → `GRAVAÇÃO`. Resposta: `{ chave, jaExistia, etapas }` (200 se já existia, 201 se criou).

### Inscrição (fluxo completo orquestrado)

`POST /inscricoes` — executa: pessoa → aluno → usuário/filial → acesso/SSO → matrícula no curso → matrícula no período letivo (contrato) → enturmação → cupom/bolsa → lançamento financeiro. **Idempotente** (reenvio retoma de onde parou).

Body:
```json
{
  "OFERTA": "OF2026-001",
  "PLANOPAGAMENTO": "PP01",
  "CPF": "12345678901",          // OU "RNM": "A123456-7" p/ estrangeiro
  "NOME": "Fulano de Tal",
  "NASCIMENTO": "1990-01-15",
  "SEXO": "M",
  "EMAIL": "fulano@email.com",
  "TELEFONE": "11987654321",
  "CEP": "01310100",             // endereço BR (opcional p/ estrangeiro)
  "ESTADO": "SP",
  "CIDADE": "3550308",           // código da cidade (INT.EDUVEM.00010)
  "BAIRRO": "123",               // código do bairro (INT.EDUVEM.00020)
  "RUA": "Av. Paulista",
  "NUMERO": "1000",
  "COMPLEMENTO": "",
  "NATURALIDADE": "3550308",     // obrigatório p/ brasileiro
  "CUPOM": "PROMO10"             // opcional
}
```

Retorno:
```json
{
  "sucesso": true,
  "mensagem": "Inscrição efetuada com sucesso!",
  "dados": { "autoLogin": true, "nextUrl": "https://.../api/sso/{token}" }
}
```

### Matrícula (etapas granulares)

| Rota | Body | Efeito |
|---|---|---|
| `POST /matriculas/curso` | `{ "RA": "...", "OFERTA": "..." }` | SaveRecord `EduHabilitacaoAlunoData` (CODSTATUS 23) |
| `POST /matriculas/periodo-letivo` | `{ "RA", "OFERTA", "PLANOPAGAMENTO" }` | Processo `EduMatriculaProcData` (gera contrato) |
| `POST /matriculas/disciplinas` | `{ "RA": "...", "OFERTA": "..." }` | Processo `EduMatriculaProcData` (enturmação por disciplina) |

### Contrato (PDF)

`POST /contratos` — body: `NOME, CPF, ESTADO, CIDADE (código), BAIRRO (código), RUA, NUMERO, COMPLEMENTO, NACIONALIDADE, NASCIMENTO (Y-m-d)`.
Pipeline: `GenerateReport` (relatório **1664**, coligada 0) → `GetGeneratedReportSize` → `GetFileChunk`. Retorna `{ "CONTEUDO": <conteúdo do PDF como o RM devolve> }`.

### Consultas

| Rota | Sentença |
|---|---|
| `GET /ofertas/{codoferta}` | INT.EDUVEM.00006 |
| `GET /ofertas/{codoferta}/planos-pagamento` | INT.EDUVEM.00013 |
| `GET /enderecos/estados` | INT.EDUVEM.00002 |
| `GET /enderecos/estados/{uf}/cidades` | INT.EDUVEM.00003 |
| `GET /enderecos/cidades/{codcidade}/bairros` | INT.EDUVEM.00004 |
| `GET /enderecos/cep/{cep}` | INT.EDUVEM.00005 |
| `GET /cupons/{codoferta}/{codplano}/{cupom}` | INT.EDUVEM.00016 |

### SSO (exceção HTML)

`GET /sso/{token}` — decripta o token (AES-GCM) e devolve página mínima com form auto-submit para o Portal Educacional TOTVS. É a única rota que retorna HTML, por exigência do mecanismo de auto-login do portal (POST de formulário no navegador do aluno).

---

## 3. Inventário RM

### DataServers (SaveRecord/ReadRecord)

| DataServer | Uso | Contexto |
|---|---|---|
| `RhuPessoaData` | Pessoa (criar/atualizar/ler) | vazio |
| `EduAlunoData` | Aluno | educacional completo |
| `EduUsuarioFilialData` | Vínculo usuário × filial | educacional completo |
| `GlbUsuarioData` | Reativação de usuário p/ SSO | vazio |
| `EduHabilitacaoAlunoData` | Pré-matrícula no curso | educacional completo |
| `EduBolsaAlunoData` | Bolsa/cupom | educacional completo |
| `RMSPRJ5495296Server` | Log de integração (ZMDLOGINTEGEDUVEM) | vazio |

### Processos (ExecuteWithXMLParams)

| Processo | Ação |
|---|---|
| `EduMatriculaProcData` | "Matricular aluno" (PL + contrato) e "Matricular aluno nas disciplinas" |
| `EduGerarLancFromContratoSliceableData` | "Gerar lançamento" financeiro |

Os XMLs ficam em `src/Support/ProcessXml.php`. São estruturalmente idênticos aos capturados do RM (compatibilidade de desserialização DataContract), com valores de sessão neutralizados: `ExecutionId` = GUID novo por chamada, `ScheduleDateTime` = agora, `HostName`/`Ip`/`NetworkUser` neutros, competência = mês corrente. Mantidos de propósito (comportamento do legado): `$CODCOLIGADA=1` e `$CODTIPOCURSO=2` no contexto dos processos, `CodStatus=23`, `CodTipoMat=7`.

### Sentenças SQL (wsConsultaSQL — codColigada `0`, codSistema `G`)

Centralizadas em `ConsultaService` (constantes `SQL_*`): 00001 status, 00002 estados, 00003 cidades, 00004 bairros, 00005 CEP, 00006 oferta, 00007 pessoa por CPF/RNM, 00008 aluno, 00009 cliente/fornecedor, 00010 cidade por código, 00011 matrícula no curso, 00013 planos de pagamento, 00014 matrícula no PL (retorna CODCONTRATO), 00016 cupom, 00017 bolsa aplicada, 00018 lançamentos, 00019 turmas/disciplinas, 00020 bairro por código.

### Operações SOAP disponíveis no RMSoapClient

`saveRecord`, `readRecord`, `readView`, `deleteRecord`, `getSchema`, `autenticaAcesso`, `realizarConsultaSQL`, `executeWithXmlParams`, `generateReport`, `getGeneratedReportSize`, `getFileChunk`.

---

## 4. Diferenças em relação ao legado

1. **Bug corrigido:** a verificação pós-matrícula no período letivo validava a variável errada (matrícula no curso); agora valida a matrícula no PL de fato (`MatriculaService::matricularNoPeriodoLetivo`).
2. **Erros transparentes:** toda falha do RM expõe `operacao`, `dataserver`, `retorno_rm` (e XMLs em debug). Nada de "erro interno" genérico.
3. **Status HTTP corretos:** 422 (validação/fluxo), 502 (RM), 404, 500 — o legado devolvia 500 para tudo.
4. **Log tolerante a falha:** gravação de log no RM nunca derruba o fluxo (fallback para error_log).
5. **Removidos:** frontend completo (www), integração Eduvem (callback), `session_start`, dependências guzzle/phpdotenv, código morto (ReadView/GetSchema agora têm endpoints), bug do `InvalidArgumentException` sem import no Format. *(Obs.: foi reintroduzido um leitor de `.env` mínimo e sem dependência — `src/Support/Env.php` — para o deploy em container; ver `docs/DEPLOY-EASYPANEL.md`.)*
6. **Escape XML:** valores de pessoa/relatório agora passam por `htmlspecialchars` (o legado interpolava cru).
7. **Eduzz:** não existia nenhuma integração no código (apenas no briefing).

## 5. Pendências recomendadas

- Rodar `composer dump-autoload` no ambiente (o autoload do vendor foi ajustado manualmente para `FMP\RMApi\` → `src/`).
- **Autenticação da API**: implementada via `API_KEY` (header `X-API-Key`) — ver `docs/DEPLOY-EASYPANEL.md`. Mantenha a chave definida em produção.
- **Rotacionar as credenciais TOTVS** e a `APP_CRYPTO_KEY` periodicamente.

## 6. Log de jobs de processo (INT.EDUVEM.00021)

Quando um processo (matrícula no PL, enturmação, gerar lançamento) falha, o RM frequentemente devolve apenas o **JobId**. O erro real (detalhes, erros, parâmetros, resumo) fica no log do job, no Monitor de Jobs.

A API tenta automaticamente buscar esse log pela sentença `INT.EDUVEM.00021` (parâmetro `JOBID_N`) e anexá-lo ao `retorno_rm` do erro. Para habilitar, cadastre a sentença no RM (Consultas SQL) retornando o texto do log do job. Modelo (confirme os nomes das tabelas do monitor de jobs na sua base com o DBA — variam por versão; procure por `SELECT name FROM sys.tables WHERE name LIKE '%JOB%'`):

```sql
/* INT.EDUVEM.00021 — texto do log de execução do job (:JOBID_N) */
SELECT L.DESCRICAO AS TEXTO
  FROM GJOBXLOG L (NOLOCK)
 WHERE L.IDJOB = :JOBID_N
 ORDER BY L.IDSEQ
```

Enquanto a sentença não existir, o erro orienta o cadastro e a consulta manual no Monitor de Jobs.

## 7. Rastreamento de etapas (/inscricoes e /alunos)

`POST /inscricoes` e `POST /alunos` devolvem a lista de etapas executadas em `dados.etapas` (sucesso) ou `etapas_concluidas` (erro), com status `OK`, `JA_EXISTIA`, `ATUALIZADA/ATUALIZADO`, `ENCONTRADO`/`NAO_ENCONTRADO`. Em erro de RM, a resposta é 422 com `etapa` (onde parou) + `etapas_concluidas` + `retorno_rm`.