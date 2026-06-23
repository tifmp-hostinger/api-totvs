<?php

declare(strict_types=1);

namespace FMP\RMApi\Services;

use FMP\RMApi\Clients\RMSoapClient;
use FMP\RMApi\Exceptions\RMException;

/**
 * Cliente/Fornecedor no RM (DataServer FinCFOBRData).
 *
 * Regras confirmadas com a FMP:
 *  - CODCOLIGADA sempre 0;
 *  - na criação envia CODCFO = 0 para o RM gerar o código (igual ao RA do aluno);
 *  - PAGREC sempre 3 (cliente e fornecedor);
 *  - PESSOAFISOUJUR derivado do documento: 11 dígitos => F (CPF), 14 => J (CNPJ);
 *  - contexto do SaveRecord usa CODSISTEMA = F (financeiro).
 */
class CfoService
{
    public const DATASERVER = 'FinCFOBRData';

    /** Coligada fixa para CFO na FMP. */
    private const CODCOLIGADA = '0';

    /** Campos opcionais: só vão ao XML quando preenchidos (evita enviar tag vazia). */
    private const CAMPOS_OPCIONAIS = [
        'CGCCFO', 'CIDENTIDADE', 'RUA', 'NUMERO', 'COMPLEMENTO', 'BAIRRO',
        'CIDADE', 'CODETD', 'CEP', 'TELEFONE', 'TELEX', 'EMAIL',
        'CODMUNICIPIO', 'ESTADOCIVIL', 'IDPAIS', 'CODTCF',
    ];

    public function __construct(
        private readonly RMSoapClient $rm,
        private readonly ConsultaService $consulta,
        private readonly array $rmConfig
    ) {
    }

    /**
     * Consulta o CFO por CPF/RNM (reusa a sentença INT.EDUVEM.00009, a mesma do Aluno).
     * Retorna o registro (CODCOLCFO, CODCFO, ...) ou null.
     */
    public function buscarPorCpfRnm(string $cpf = '', string $rnm = ''): ?array
    {
        return $this->consulta->cliForPorCpfRnm($cpf, $rnm);
    }

    /**
     * Cria o CFO. Como envia CODCFO = 0, o RM gera o código e devolve a chave
     * "CODCOLIGADA;CODCFO".
     */
    public function salvar(array $p): string
    {
        $p = self::sanitizar($p);

        // Coligada é sempre 0; CODCFO é 0 na criação (RM gera).
        $p['CODCOLIGADA'] = self::CODCOLIGADA;
        $p['CODCFO']      = (string) ($p['CODCFO'] ?? '0');

        $xml = self::buildXml($p);

        $contexto = [
            'CODCOLIGADA' => self::CODCOLIGADA,
            'CODSISTEMA'  => 'F',
            'CODUSUARIO'  => $this->rmConfig['usuario_servico'] ?? 'integra.eduvem',
        ];

        $result = $this->rm->saveRecord(self::DATASERVER, $xml, $contexto);

        // Esperado: "CODCOLIGADA;CODCFO".
        $parts = explode(';', $result, 2);
        if (count($parts) < 2 || $parts[0] !== self::CODCOLIGADA) {
            throw new RMException(
                'O RM rejeitou a gravação do cliente/fornecedor',
                operacao: 'SaveRecord',
                dataServer: self::DATASERVER,
                contexto: $contexto,
                xmlEnviado: $xml,
                retornoRm: $result
            );
        }

        return $result;
    }

    /** Remove máscara dos campos que o RM grava como dígitos puros. */
    private static function sanitizar(array $p): array
    {
        foreach (['CGCCFO', 'CEP', 'TELEFONE', 'TELEX'] as $campo) {
            if (isset($p[$campo]) && $p[$campo] !== '') {
                $p[$campo] = preg_replace('/\D/', '', (string) $p[$campo]);
            }
        }
        return $p;
    }

    public static function buildXml(array $p): string
    {
        $esc = fn($v) => htmlspecialchars((string) $v, ENT_XML1, 'UTF-8');

        $codcol = (string) ($p['CODCOLIGADA'] ?? self::CODCOLIGADA);
        $codcfo = (string) ($p['CODCFO'] ?? '0');

        // Categoria automática: 14 dígitos => CNPJ (J); caso contrário CPF (F).
        // Respeita PESSOAFISOUJUR explícito se vier.
        $doc = preg_replace('/\D/', '', (string) ($p['CGCCFO'] ?? ''));
        $pessoa = $p['PESSOAFISOUJUR'] ?? (strlen($doc) === 14 ? 'J' : 'F');

        // Campos obrigatórios (sempre presentes).
        $obrig = [
            'CODCOLIGADA'    => $codcol,
            'CODCFO'         => $codcfo,
            'NOMEFANTASIA'   => $p['NOMEFANTASIA'] ?? ($p['NOME'] ?? ''),
            'NOME'           => $p['NOME'] ?? '',
            'PAGREC'         => $p['PAGREC'] ?? '3',
            'ATIVO'          => $p['ATIVO'] ?? '1',
            'PESSOAFISOUJUR' => $pessoa,
            'IDCFO'          => $p['IDCFO'] ?? '0',
        ];

        $fcfo = '';
        foreach ($obrig as $tag => $val) {
            $fcfo .= "                <{$tag}>" . $esc($val) . "</{$tag}>\n";
        }
        foreach (self::CAMPOS_OPCIONAIS as $tag) {
            if (isset($p[$tag]) && $p[$tag] !== '') {
                $fcfo .= "                <{$tag}>" . $esc($p[$tag]) . "</{$tag}>\n";
            }
        }

        return "<FinCFOBR>\n"
            . "            <FCFO>\n"
            . $fcfo
            . "            </FCFO>\n"
            . "            <FCFOCOMPL>\n"
            . "                <CODCOLIGADA>" . $esc($codcol) . "</CODCOLIGADA>\n"
            . "                <CODCFO>" . $esc($codcfo) . "</CODCFO>\n"
            . "            </FCFOCOMPL>\n"
            . "        </FinCFOBR>";
    }
}
