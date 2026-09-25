<?php

declare(strict_types=1);

namespace FMP\RMApi\Controllers;

use FMP\RMApi\Helpers\Json;
use FMP\RMApi\Services\AssinaturaService;
use FMP\RMApi\Services\ContratoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ContratoController
{
    public function __construct(
        private readonly ContratoService $contratoService,
        private readonly AssinaturaService $assinaturaService
    ) {
    }

    /**
     * POST /contratos — gera o PDF do contrato de matrícula.
     * Body: NOME, CPF, ESTADO, CIDADE, BAIRRO, RUA, NUMERO,
     *       COMPLEMENTO, NACIONALIDADE, NASCIMENTO (Y-m-d)
     */
    public function gerar(Request $request, Response $response, array $args = []): Response
    {
        $data = (array) $request->getParsedBody();

        $conteudo = $this->contratoService->gerar($data);

        return Json::success('Contrato gerado com sucesso.', ['CONTEUDO' => $conteudo]);
    }

    /**
     * POST /contratos/assinatura — envia o contrato do aluno para a TOTVS
     * Assinatura Eletrônica (processo EduTotvsSignContratoSliceableProcData).
     * Body: RA, OFERTA, NOMEDOCUMENTO, CODCONTRATO?, IDREPORT?,
     *       CODCOLIGADAREPORT?, DRY_RUN?
     */
    public function enviarAssinatura(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        $dados = $this->assinaturaService->enviar($body);

        return Json::success(
            !empty($dados['dry_run'])
                ? 'DRY_RUN: XML da assinatura gerado, nada foi enviado ao RM.'
                : 'Contrato enviado ao RM para assinatura eletrônica.',
            $dados
        );
    }
}
