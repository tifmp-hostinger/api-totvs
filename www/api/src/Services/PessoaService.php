<?php

declare(strict_types=1);

namespace FMP\RMApi\Services;

use FMP\RMApi\Clients\RMSoapClient;
use FMP\RMApi\Exceptions\RMException;

/**
 * Operações de Pessoa no RM (DataServer RhuPessoaData).
 */
class PessoaService
{
    public const DATASERVER = 'RhuPessoaData';

    public function __construct(
        private readonly RMSoapClient $rm,
        private readonly ConsultaService $consulta
    ) {
    }

    /**
     * Busca o registro completo da pessoa pelo CODIGO (ReadRecord).
     */
    public function buscar(string|int $codigo): ?array
    {
        $record = $this->rm->readRecord(self::DATASERVER, [(string) $codigo]);

        return $record['PPessoa'] ?? null;
    }

    /**
     * Busca pessoa por CPF ou RNM. Retorna o registro completo ou null.
     */
    public function buscarPorCpfRnm(string $cpf = '', string $rnm = ''): ?array
    {
        $found = $this->consulta->pessoaPorCpfRnm($cpf, $rnm);

        if ($found === null) {
            return null;
        }

        return $this->buscar($found['CODIGO']);
    }

    /**
     * Cria (CODIGO = 0) ou atualiza (CODIGO > 0) a pessoa via SaveRecord.
     * Retorna o CODPESSOA gravado.
     *
     * Campos esperados em $p (já validados/normalizados):
     * CODIGO, NOME, DTNASCIMENTO, ESTADONATAL, NATURALIDADE, SEXO,
     * NACIONALIDADE, RUA, NUMERO, COMPLEMENTO, BAIRRO, ESTADO, CIDADE,
     * CEP, PAIS, CPF, TELEFONE1, EMAIL, CODMUNICIPIO, CODNATURALIDADE,
     * IDPAIS, NROREGGERAL
     */
    public function salvar(array $p): string
    {
        $p = self::sanitizarDocumentos($p);

        $xml = self::buildXml($p);

        $result = $this->rm->saveRecord(self::DATASERVER, $xml);

        if (!is_numeric($result)) {
            throw new RMException(
                'O RM rejeitou a gravação da pessoa',
                operacao: 'SaveRecord',
                dataServer: self::DATASERVER,
                xmlEnviado: $xml,
                retornoRm: $result
            );
        }

        return $result;
    }

    /**
     * Remove máscara dos campos que o RM grava como dígitos puros.
     * A coluna PPESSOA.CPF (e CEP) tem tamanho fixo: enviar com pontos/traços
     * estoura ("String or binary data would be truncated ... column 'CPF'").
     */
    private static function sanitizarDocumentos(array $p): array
    {
        foreach (['CPF', 'CEP', 'TELEFONE1'] as $campo) {
            if (isset($p[$campo]) && $p[$campo] !== '') {
                $p[$campo] = preg_replace('/\D/', '', (string) $p[$campo]);
            }
        }
        return $p;
    }

    /** Campos da PPessoa gravados por esta rota, na ordem esperada pelo RM. */
    private const CAMPOS = [
        'NOME', 'DTNASCIMENTO', 'ESTADONATAL', 'NATURALIDADE', 'SEXO',
        'NACIONALIDADE', 'RUA', 'NUMERO', 'COMPLEMENTO', 'BAIRRO', 'ESTADO',
        'CIDADE', 'CEP', 'PAIS', 'CPF', 'TELEFONE1', 'EMAIL', 'CODMUNICIPIO',
        'CODNATURALIDADE', 'IDPAIS', 'NROREGGERAL',
    ];

    /**
     * Monta o XML do RhuPessoaData.
     *
     * CRIAÇÃO (CODIGO = 0): emite todas as tags, inclusive as vazias — é o
     * contrato que o RM espera para um registro novo.
     *
     * ATUALIZAÇÃO (CODIGO ≠ 0): emite SOMENTE os campos informados. Emitir a
     * tag vazia faz o RM sobrescrever o valor existente com vazio, ou seja,
     * uma atualização parcial APAGAVA os campos não enviados (é assim que uma
     * data de nascimento some e vira 01/01/1970 na releitura).
     */
    public static function buildXml(array $p): string
    {
        $esc = fn($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $codigo = (string) ($p['CODIGO'] ?? '0');
        $criando = trim($codigo) === '' || (string) (int) $codigo === '0';

        $campos = '';
        foreach (self::CAMPOS as $tag) {
            $valor = (string) ($p[$tag] ?? '');
            if (!$criando && $valor === '') {
                continue;   // atualização: não mexe no que não foi informado
            }
            $campos .= "                <{$tag}>" . $esc($valor) . "</{$tag}>\n";
        }

        $codigoEsc = $esc($codigo);

        return "        <RhuPessoa>\n"
            . "            <PPessoa>\n"
            . "                <CODIGO>{$codigoEsc}</CODIGO>\n"
            . $campos
            . "            </PPessoa>\n"
            . "            <VPCompl>\n"
            . "                <CODPESSOA>{$codigoEsc}</CODPESSOA>\n"
            . "            </VPCompl>\n"
            . "        </RhuPessoa>";
    }
}
