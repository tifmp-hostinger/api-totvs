# Deploy no EasyPanel — FMP RM-API

Guia de operação: variáveis de ambiente, build (Dockerfile), tuning de runtime,
diagnóstico e troubleshooting. Escrito a partir dos problemas reais enfrentados
ao subir a API no EasyPanel.

---

## 1. Como a aplicação lê configuração

A app **não usa** nenhuma biblioteca de `.env` pesada. A configuração vem de
**variáveis de ambiente** lidas por `src/Support/Env.php`, nesta ordem de
precedência:

1. Ambiente real do container (`getenv`, `$_ENV`, `$_SERVER`) — **EasyPanel/Docker**;
2. Arquivo `.env` na raiz de `www/api/` (carregado por `Env::load()` em `public/index.php`), **sem sobrescrever** o que já veio do ambiente real.

> **Implicação prática:** no EasyPanel, prefira definir tudo na aba **Environment**
> (variáveis reais). Um arquivo `.env` só é lido se existir dentro da imagem, e o
> `.gitignore` ignora `www/api/.env` — ou seja, em deploy via Git o `.env` **não vai
> junto**. Por isso a fonte de verdade é a aba Environment.

### Variáveis de ambiente

| Variável | Obrigatória | Exemplo / observação |
|---|---|---|
| `TOTVS_WS_URL` | **sim** | `https://fundacaoescola114384.rm.cloudtotvs.com.br:8051` (host + porta do webservice do RM; confirme com o DBA) |
| `TOTVS_WS_USER` | **sim** | usuário de integração do RM |
| `TOTVS_WS_PASSWORD` | **sim** | senha do usuário de integração |
| `APP_DEBUG` | não | `true` para expor `xml_enviado`/`xml_retornado`/`soap_fault` nos erros. Padrão `false` |
| `APP_BASE` | não | base path lógico. **Hoje ignorado** — `index.php` usa `setBasePath('')` (rotas na raiz) |
| `APP_CRYPTO_KEY` | só p/ SSO | chave de **32 bytes exatos** (AES-256-GCM do `/sso/{token}`) |
| `APP_CRYPTO_METHOD` | não | padrão `aes-256-gcm` |

> Se `TOTVS_WS_URL` vier **vazia**, o SOAP monta a URL como `/wsDataServer/MEX?wsdl`
> (sem host) e estoura: `Couldn't load from '/wsDataServer/MEX?wsdl' : failed to load
> external entity`. **Esse erro = variável de ambiente faltando/não aplicada.**

---

## 2. Base path: a app roda na raiz

`public/index.php` faz `setBasePath('')`. As rotas são `/pessoas`, `/rm/test`,
`/inscricoes`… **sem** o prefixo `/api`. Use a URL do serviço direto:

```
https://SEU-SERVIDOR/rm/test
https://SEU-SERVIDOR/pessoas
```

(O `.env.example` traz `APP_BASE="/api"`, mas essa linha está desativada no bootstrap.)

---

## 3. Build (Dockerfile) e tuning de runtime

A imagem base é `php:8.3-apache` com as extensões `curl` e `soap`. Além do build
padrão, o `Dockerfile` aplica um **perfil de produção** — e o motivo de cada item
importa:

### PHP (`$PHP_INI_DIR/conf.d/zz-app.ini`)

| Diretiva | Valor | Por quê |
|---|---|---|
| `memory_limit` | `256M` | O WSDL do `wsDataServer` do RM é grande; serializar `SaveRecord` consome memória. É um **teto por requisição**, não memória reservada |
| `max_execution_time` | `300` | Processos do RM (matrícula, contrato) podem demorar |
| `max_input_time` | `300` | idem, na entrada |
| `default_socket_timeout` | `300` | **quanto o PHP espera o RM responder** no SOAP. Era 60s por padrão e cortava processos lentos |
| `post_max_size` / `upload_max_filesize` | `20M` | folga para payloads maiores |
| `log_errors` + `error_log=/dev/stderr` | on | **faz qualquer fatal/segfault aparecer nos Logs do EasyPanel** |
| `display_errors` | off | não vaza erro na resposta |

### Apache

| Item | Valor | Por quê |
|---|---|---|
| `Timeout` / `ProxyTimeout` | `300` | acompanha os processos lentos do RM |
| `MaxRequestWorkers` (prefork) | `8` | **regra de ouro:** `workers × memory_limit ≤ RAM do container`. Com 256M × 8 ≈ 2 GB de pico |
| `MaxConnectionsPerChild` | `500` | recicla workers, evita vazamento de memória ao longo do tempo |

### SOAP (`src/Clients/RMSoapClient.php`)

`connection_timeout=30` (conexão), `cache_wsdl=WSDL_CACHE_BOTH` (cacheia o WSDL
grande) e `keep_alive=false`. Assim um RM lento/indisponível vira **exceção
tratável** (502 JSON do app) em vez de derrubar o processo.

---

## 4. Dimensionamento (RAM × workers × timeout)

- `MaxRequestWorkers × memory_limit` precisa **caber na RAM do container**, com folga.
  Se a RAM do container for menor que isso, o Linux mata o processo (OOM) e o EasyPanel
  mostra o 502 "Service is not reachable".
- Defina a RAM em **EasyPanel → serviço → Resources**. Recomendado: **2 GB** (pode subir
  numa VPS folgada). Se aumentar a RAM e quiser mais concorrência, suba `MaxRequestWorkers`
  na mesma proporção no `Dockerfile`.
- **Timeout infinito é perigoso:** cada requisição lenta segura um worker; requisições
  travadas o suficiente esgotam todos os workers e derrubam a API inteira. Use um teto
  finito e generoso (300s). Para jobs realmente longos, o caminho certo é **assíncrono**
  (responder com um id de job e processar em segundo plano), não aumentar o timeout.

---

## 5. Diagnóstico rápido

| Endpoint | Para quê |
|---|---|
| `GET /teste.php` | Mostra (sem vazar segredo) se `TOTVS_WS_URL/USER/PASSWORD` chegam ao PHP, versão, ext_soap, etc. |
| `GET /status` | A app sobe e responde |
| `GET /rm/test` | Conexão + credenciais SOAP com o RM (sentença `INT.EDUVEM.00001`) |

Exemplo de POST (note o `Content-Type: application/json`; sem ele o corpo não é lido):

```bash
curl -i -X POST "https://SEU-SERVIDOR/pessoas" \
  -H "Content-Type: application/json" \
  -d '{"CODIGO":0,"NOME":"Teste","CPF":"00000000000","EMAIL":"teste@fmp.com.br"}'
```

---

## 6. Troubleshooting — tabela de sintomas

| Sintoma | Causa provável | Ação |
|---|---|---|
| JSON `Couldn't load from '/wsDataServer/MEX?wsdl'` | `TOTVS_WS_URL` vazia/não aplicada | Definir variáveis na aba Environment e **redeploy** (variável só vale após reiniciar) |
| HTML do EasyPanel **"Service is not reachable"** (502) num POST | O processo PHP **morreu** na requisição (OOM/segfault) — não é erro do app (erros do app são JSON) | Ver **Logs**; subir RAM do container e/ou `memory_limit`; conferir `workers × memory_limit ≤ RAM` |
| 502 só em processos **lentos** do RM | Timeout curto sendo cortado (PHP socket / Apache / proxy) | Timeouts já em 300s no build; conferir também timeout do proxy do EasyPanel |
| POST "funciona" mas a pessoa vem **vazia** | n8n sem `Content-Type: application/json` | No nó HTTP Request: **Send Body → JSON** |
| `GET` funciona e `POST` falha com erro **antigo** | Execução **stale** no n8n / cache | Reexecutar do zero; comparar com `curl` direto |
| Erro de gravação com mensagem do RM em `retorno_rm` | Validação/contexto do RM (ex.: falta `CODCOLIGADA`) | Ligar `APP_DEBUG=true` e ler `xml_enviado`/`xml_retornado` |

### Onde ler os logs
EasyPanel → seu serviço → aba **Logs**. Com `error_log=/dev/stderr`, fatais do PHP e
linhas do Apache (inclusive `child pid ... Segmentation fault`) aparecem ali.

---

## 7. Checklist de deploy

1. Variáveis na aba **Environment** (`TOTVS_WS_URL`, `TOTVS_WS_USER`, `TOTVS_WS_PASSWORD`; opcional `APP_DEBUG`, `APP_CRYPTO_KEY`).
2. **Resources**: RAM ≥ 2 GB; porta do serviço = **80** (a imagem `php:apache` escuta na 80).
3. Deploy/rebuild (o tuning está no `Dockerfile` — exige novo build).
4. Validar nesta ordem: `/teste.php` → `/status` → `/rm/test` → `POST /pessoas`.
5. Em produção, voltar `APP_DEBUG` para `false`.
