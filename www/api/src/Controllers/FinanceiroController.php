<?php

declare(strict_types=1);

namespace FMP\RMApi\Controllers;

use FMP\RMApi\Helpers\Json;
use FMP\RMApi\Services\BaixaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Operações financeiras que disparam processos do RM (wsProcess).
 */
final class FinanceiroController
{
    public function __construct(private readonly BaixaService $baixaService)
    {
    }

    /**
     * POST /financeiro/baixas — baixa (quita) um lançamento financeiro.
     *
     * Erros são convertidos pelo handler central: ValidationException -> 422,
     * RMException -> 502 (com operacao/retorno_rm e, em debug, os XMLs).
     */
    public function baixar(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        $dados = $this->baixaService->baixar($body);

        return Json::success('Baixa de lançamento enviada ao RM.', $dados);
    }
}
