<?php

declare(strict_types=1);

namespace FMP\RMApi\Middleware;

use FMP\RMApi\Helpers\Json;
use FMP\RMApi\Services\RouteControlService;
use FMP\RMApi\Support\RouteCatalog;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Routing\RouteContext;

/**
 * Portão de rotas do painel administrativo.
 *
 * Deve ser o middleware MAIS INTERNO (adicionado antes dos demais no
 * index.php), para rodar depois do roteamento — assim a rota já resolvida
 * pelo Slim é identificada no catálogo pelo par método + pattern.
 *
 *  - Rota desativada pelo painel  → 503 com o motivo (nem chega ao controller).
 *  - Rota ativa / fora do catálogo → segue o fluxo normal.
 *  - Todo acesso a rota catalogada é contabilizado (hits, último status,
 *    duração) para exibição no painel. Estatística nunca derruba a requisição.
 */
final class RouteGate
{
    public function __construct(private readonly RouteControlService $controle)
    {
    }

    public function __invoke(Request $request, Handler $handler): Response
    {
        $rota = RouteContext::fromRequest($request)->getRoute();
        if ($rota === null) {
            return $handler->handle($request);
        }

        $id = RouteCatalog::idPorPadrao($request->getMethod(), $rota->getPattern());
        if ($id === null) {
            return $handler->handle($request);
        }

        if (!$this->controle->ativa($id)) {
            $estado = $this->controle->estado()[$id] ?? [];
            $this->controle->registrar($id, 503, 0.0, true);

            return Json::error(
                'Esta rota está temporariamente desativada pelo painel de controle da API.',
                [
                    'rota'          => $request->getMethod() . ' ' . $rota->getPattern(),
                    'motivo'        => (string) ($estado['motivo'] ?? ''),
                    'desativada_em' => (string) ($estado['atualizado_em'] ?? ''),
                ],
                503
            );
        }

        $inicio = microtime(true);
        try {
            $response = $handler->handle($request);
        } catch (\Throwable $e) {
            // O error handler central converte a exceção em resposta; aqui
            // só registramos que a chamada terminou em erro (status 500 como
            // aproximação — o status real é decidido depois, no handler).
            $this->controle->registrar($id, 500, (microtime(true) - $inicio) * 1000);
            throw $e;
        }

        $this->controle->registrar($id, $response->getStatusCode(), (microtime(true) - $inicio) * 1000);

        return $response;
    }
}
