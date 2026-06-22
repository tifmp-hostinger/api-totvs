# cURLs de todos os endpoints — FMP RM-API

> Defina a variável de ambiente antes (ou substitua direto na URL):
>
> ```bash
> export BASE_URL="https://SEU-SERVIDOR/api"
> ```
>
> **n8n:** em um nó *HTTP Request*, use *Import cURL* e cole qualquer comando abaixo (troque $BASE_URL pela URL real).
> Alternativa: importe `docs/postman_collection.json` no Postman/Insomnia (variável `baseUrl`).


## Sistema

### Health check

`GET /status` — Verifica disponibilidade do RM (INT.EDUVEM.00001)


```bash
curl -X GET "$BASE_URL/status" \
  -H "Accept: application/json"
```


## RM genérico

### Testar conexão

`GET /rm/test` — Valida conectividade e credenciais SOAP


```bash
curl -X GET "$BASE_URL/rm/test" \
  -H "Accept: application/json"
```

### Schema de DataServer

`GET /rm/schema/RhuPessoaData` — Schema parseado (tabelas, campos, chaves). Acrescente ?raw=1 para o XSD bruto


> Troque RhuPessoaData pelo DataServer desejado (EduAlunoData, EduHabilitacaoAlunoData...)


```bash
curl -X GET "$BASE_URL/rm/schema/RhuPessoaData" \
  -H "Accept: application/json"
```

### Consulta SQL cadastrada

`POST /rm/sql/INT.EDUVEM.00007` — Executa qualquer sentença SQL cadastrada no RM


Body de exemplo:

```json
{
  "parametros": {
    "CPF_S": "12345678901",
    "RNM_S": "0"
  },
  "codcoligada": "0",
  "codsistema": "G"
}
```


```bash
curl -X POST "$BASE_URL/rm/sql/INT.EDUVEM.00007" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "parametros": {
    "CPF_S": "12345678901",
    "RNM_S": "0"
  },
  "codcoligada": "0",
  "codsistema": "G"
}'
```

### ReadRecord genérico

`POST /rm/read/RhuPessoaData` — Lê um registro pela chave primária


> chave = partes da PK na ordem (separadas no RM por ';')


Body de exemplo:

```json
{
  "chave": [
    "12345"
  ],
  "contexto": {}
}
```


```bash
curl -X POST "$BASE_URL/rm/read/RhuPessoaData" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "chave": [
    "12345"
  ],
  "contexto": {}
}'
```

### ReadView genérico

`POST /rm/view/GlbColigadaData` — Consulta com filtro SQL na view do DataServer


Body de exemplo:

```json
{
  "filtro": "CODCOLIGADA=1",
  "contexto": {}
}
```


```bash
curl -X POST "$BASE_URL/rm/view/GlbColigadaData" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "filtro": "CODCOLIGADA=1",
  "contexto": {}
}'
```

### SaveRecord genérico

`POST /rm/save/RhuPessoaData` — Grava um XML qualquer no DataServer (uso para diagnóstico/automação avançada)


Body de exemplo:

```json
{
  "xml": "<RhuPessoa><PPessoa><CODIGO>0</CODIGO><NOME>Teste Da Silva</NOME><DTNASCIMENTO>1990-01-15</DTNASCIMENTO><SEXO>M</SEXO><CPF>12345678901</CPF><EMAIL>teste@email.com</EMAIL></PPessoa><VPCompl><CODPESSOA>0</CODPESSOA></VPCompl></RhuPessoa>",
  "contexto": {
    "CODCOLIGADA": "1",
    "CODSISTEMA": "S",
    "CODUSUARIO": "integra.eduvem"
  }
}
```


```bash
curl -X POST "$BASE_URL/rm/save/RhuPessoaData" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "xml": "<RhuPessoa><PPessoa><CODIGO>0</CODIGO><NOME>Teste Da Silva</NOME><DTNASCIMENTO>1990-01-15</DTNASCIMENTO><SEXO>M</SEXO><CPF>12345678901</CPF><EMAIL>teste@email.com</EMAIL></PPessoa><VPCompl><CODPESSOA>0</CODPESSOA></VPCompl></RhuPessoa>",
  "contexto": {
    "CODCOLIGADA": "1",
    "CODSISTEMA": "S",
    "CODUSUARIO": "integra.eduvem"
  }
}'
```


## Pessoa

### Criar/atualizar pessoa

`POST /pessoas` — CODIGO=0 ou ausente cria; CODIGO>0 atualiza. Retorna CODPESSOA


> Em caso de erro de validação do RM, o retorno traz retorno_rm com a mensagem original


Body de exemplo:

```json
{
  "CODIGO": 0,
  "NOME": "Fulano de Tal",
  "DTNASCIMENTO": "1990-01-15",
  "ESTADONATAL": "SP",
  "NATURALIDADE": "São Paulo",
  "SEXO": "M",
  "NACIONALIDADE": "10",
  "RUA": "Av. Paulista",
  "NUMERO": "1000",
  "COMPLEMENTO": "Apto 101",
  "BAIRRO": "Bela Vista",
  "ESTADO": "SP",
  "CIDADE": "São Paulo",
  "CEP": "01310100",
  "PAIS": "Brasil",
  "CPF": "12345678901",
  "TELEFONE1": "11987654321",
  "EMAIL": "fulano@email.com",
  "CODMUNICIPIO": "3550308",
  "CODNATURALIDADE": "3550308",
  "IDPAIS": 1,
  "NROREGGERAL": ""
}
```


```bash
curl -X POST "$BASE_URL/pessoas" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "CODIGO": 0,
  "NOME": "Fulano de Tal",
  "DTNASCIMENTO": "1990-01-15",
  "ESTADONATAL": "SP",
  "NATURALIDADE": "São Paulo",
  "SEXO": "M",
  "NACIONALIDADE": "10",
  "RUA": "Av. Paulista",
  "NUMERO": "1000",
  "COMPLEMENTO": "Apto 101",
  "BAIRRO": "Bela Vista",
  "ESTADO": "SP",
  "CIDADE": "São Paulo",
  "CEP": "01310100",
  "PAIS": "Brasil",
  "CPF": "12345678901",
  "TELEFONE1": "11987654321",
  "EMAIL": "fulano@email.com",
  "CODMUNICIPIO": "3550308",
  "CODNATURALIDADE": "3550308",
  "IDPAIS": 1,
  "NROREGGERAL": ""
}'
```

### Buscar pessoa por código

`GET /pessoas/12345` — ReadRecord RhuPessoaData


```bash
curl -X GET "$BASE_URL/pessoas/12345" \
  -H "Accept: application/json"
```

### Buscar pessoa por CPF

`POST /pessoas/busca` — Localiza por CPF (brasileiro)


Body de exemplo:

```json
{
  "CPF": "12345678901"
}
```


```bash
curl -X POST "$BASE_URL/pessoas/busca" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "CPF": "12345678901"
}'
```

### Buscar pessoa por RNM (estrangeiro)

`POST /pessoas/busca` — Localiza por RNM


Body de exemplo:

```json
{
  "RNM": "A123456-7"
}
```


```bash
curl -X POST "$BASE_URL/pessoas/busca" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "RNM": "A123456-7"
}'
```


## Aluno

### Criar/atualizar aluno

`POST /alunos` — Cria o aluno da pessoa na coligada. Retorna CHAVE = CODCOLIGADA;RA


Body de exemplo:

```json
{
  "CODPESSOA": 12345,
  "CODCOLIGADA": 1,
  "CODTIPOCURSO": 2,
  "CODFILIAL": 1,
  "CPF": "12345678901",
  "RNM": ""
}
```


```bash
curl -X POST "$BASE_URL/alunos" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "CODPESSOA": 12345,
  "CODCOLIGADA": 1,
  "CODTIPOCURSO": 2,
  "CODFILIAL": 1,
  "CPF": "12345678901",
  "RNM": ""
}'
```

### Buscar aluno

`GET /alunos/1/12345` — Formato: /alunos/{codcoligada}/{codpessoa}. Retorna RA, CODUSUARIO, SENHAPADRAO...


```bash
curl -X GET "$BASE_URL/alunos/1/12345" \
  -H "Accept: application/json"
```


## Inscrição

### Inscrição completa (brasileiro)

`POST /inscricoes` — Fluxo orquestrado: pessoa → aluno → matrículas → enturmação → cupom → lançamento. Idempotente


> CIDADE/BAIRRO/NATURALIDADE são códigos (use /enderecos para obtê-los). CUPOM é opcional


Body de exemplo:

```json
{
  "OFERTA": "OF2026-001",
  "PLANOPAGAMENTO": "PP01",
  "CPF": "12345678901",
  "NOME": "Fulano de Tal",
  "NASCIMENTO": "1990-01-15",
  "SEXO": "M",
  "EMAIL": "fulano@email.com",
  "TELEFONE": "11987654321",
  "CEP": "01310100",
  "ESTADO": "SP",
  "CIDADE": "3550308",
  "BAIRRO": "123",
  "RUA": "Av. Paulista",
  "NUMERO": "1000",
  "COMPLEMENTO": "",
  "NATURALIDADE": "3550308",
  "CUPOM": "PROMO10"
}
```


```bash
curl -X POST "$BASE_URL/inscricoes" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "OFERTA": "OF2026-001",
  "PLANOPAGAMENTO": "PP01",
  "CPF": "12345678901",
  "NOME": "Fulano de Tal",
  "NASCIMENTO": "1990-01-15",
  "SEXO": "M",
  "EMAIL": "fulano@email.com",
  "TELEFONE": "11987654321",
  "CEP": "01310100",
  "ESTADO": "SP",
  "CIDADE": "3550308",
  "BAIRRO": "123",
  "RUA": "Av. Paulista",
  "NUMERO": "1000",
  "COMPLEMENTO": "",
  "NATURALIDADE": "3550308",
  "CUPOM": "PROMO10"
}'
```

### Inscrição completa (estrangeiro)

`POST /inscricoes` — Sem CPF/endereço BR: usa RNM; endereço fica como 'Outro'


Body de exemplo:

```json
{
  "OFERTA": "OF2026-001",
  "PLANOPAGAMENTO": "PP01",
  "RNM": "A123456-7",
  "NOME": "John Doe Smith",
  "NASCIMENTO": "1985-06-20",
  "SEXO": "M",
  "EMAIL": "john@email.com",
  "TELEFONE": "11987654321"
}
```


```bash
curl -X POST "$BASE_URL/inscricoes" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "OFERTA": "OF2026-001",
  "PLANOPAGAMENTO": "PP01",
  "RNM": "A123456-7",
  "NOME": "John Doe Smith",
  "NASCIMENTO": "1985-06-20",
  "SEXO": "M",
  "EMAIL": "john@email.com",
  "TELEFONE": "11987654321"
}'
```


## Matrícula

### Matrícula no curso

`POST /matriculas/curso` — Pré-matrícula (CODSTATUS 23) via EduHabilitacaoAlunoData


Body de exemplo:

```json
{
  "RA": "000123",
  "OFERTA": "OF2026-001"
}
```


```bash
curl -X POST "$BASE_URL/matriculas/curso" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "RA": "000123",
  "OFERTA": "OF2026-001"
}'
```

### Matrícula no período letivo

`POST /matriculas/periodo-letivo` — Processo 'Matricular aluno' — gera o contrato. Retorna a linha com CODCONTRATO


Body de exemplo:

```json
{
  "RA": "000123",
  "OFERTA": "OF2026-001",
  "PLANOPAGAMENTO": "PP01"
}
```


```bash
curl -X POST "$BASE_URL/matriculas/periodo-letivo" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "RA": "000123",
  "OFERTA": "OF2026-001",
  "PLANOPAGAMENTO": "PP01"
}'
```

### Matrícula nas disciplinas (enturmação)

`POST /matriculas/disciplinas` — Processo 'Matricular aluno nas disciplinas' para cada turma da oferta


Body de exemplo:

```json
{
  "RA": "000123",
  "OFERTA": "OF2026-001"
}
```


```bash
curl -X POST "$BASE_URL/matriculas/disciplinas" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "RA": "000123",
  "OFERTA": "OF2026-001"
}'
```


## Contrato

### Gerar contrato (PDF)

`POST /contratos` — GenerateReport 1664 → GetGeneratedReportSize → GetFileChunk. Retorna CONTEUDO


> CIDADE e BAIRRO são códigos; a API resolve os nomes via INT.EDUVEM.00010/00020


Body de exemplo:

```json
{
  "NOME": "Fulano de Tal",
  "CPF": "12345678901",
  "ESTADO": "SP",
  "CIDADE": "3550308",
  "BAIRRO": "123",
  "RUA": "Av. Paulista",
  "NUMERO": "1000",
  "COMPLEMENTO": "",
  "NACIONALIDADE": "Brasileira",
  "NASCIMENTO": "1990-01-15"
}
```


```bash
curl -X POST "$BASE_URL/contratos" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
  "NOME": "Fulano de Tal",
  "CPF": "12345678901",
  "ESTADO": "SP",
  "CIDADE": "3550308",
  "BAIRRO": "123",
  "RUA": "Av. Paulista",
  "NUMERO": "1000",
  "COMPLEMENTO": "",
  "NACIONALIDADE": "Brasileira",
  "NASCIMENTO": "1990-01-15"
}'
```


## Oferta

### Dados da oferta

`GET /ofertas/OF2026-001` — INT.EDUVEM.00006


```bash
curl -X GET "$BASE_URL/ofertas/OF2026-001" \
  -H "Accept: application/json"
```

### Planos de pagamento da oferta

`GET /ofertas/OF2026-001/planos-pagamento` — INT.EDUVEM.00013


```bash
curl -X GET "$BASE_URL/ofertas/OF2026-001/planos-pagamento" \
  -H "Accept: application/json"
```


## Endereço

### Estados

`GET /enderecos/estados` — INT.EDUVEM.00002


```bash
curl -X GET "$BASE_URL/enderecos/estados" \
  -H "Accept: application/json"
```

### Cidades do estado

`GET /enderecos/estados/SP/cidades` — INT.EDUVEM.00003


```bash
curl -X GET "$BASE_URL/enderecos/estados/SP/cidades" \
  -H "Accept: application/json"
```

### Bairros da cidade

`GET /enderecos/cidades/3550308/bairros` — INT.EDUVEM.00004


```bash
curl -X GET "$BASE_URL/enderecos/cidades/3550308/bairros" \
  -H "Accept: application/json"
```

### Endereço por CEP

`GET /enderecos/cep/01310100` — INT.EDUVEM.00005


```bash
curl -X GET "$BASE_URL/enderecos/cep/01310100" \
  -H "Accept: application/json"
```


## Cupom

### Validar cupom

`GET /cupons/OF2026-001/PP01/PROMO10` — Formato: /cupons/{codoferta}/{codplano}/{cupom} (INT.EDUVEM.00016)


```bash
curl -X GET "$BASE_URL/cupons/OF2026-001/PP01/PROMO10" \
  -H "Accept: application/json"
```


## SSO

### Auto-login no Portal (HTML)

`GET /sso/TOKEN_GERADO_PELA_INSCRICAO` — Único endpoint HTML — consumir no navegador do aluno via redirect do nextUrl


> O token vem no retorno de POST /inscricoes (dados.nextUrl)


```bash
curl -X GET "$BASE_URL/sso/TOKEN_GERADO_PELA_INSCRICAO" \
  -H "Accept: application/json"
```
