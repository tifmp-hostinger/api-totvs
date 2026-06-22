<?php

declare(strict_types=1);

namespace FMP\RMApi\Middleware;

use FMP\RMApi\Helpers\Json;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Autenticação simples por API key.
 *
 * Regras:
 *  - Só fica ATIVA quando a env API_KEY está definida (não vazia). Sem ela,
 *    libera tudo e registra um aviso — assim ninguém fica trancado pra fora
 *    antes de configurar a chave.
 *  - Compara o header "X-API-Key" (ou "Authorization: Bearer <chave>") com a
 *    API_KEY usando hash_equals (comparação à prova de timing).
 *  - Libera requisições OPTIONS (preflight CORS) e as rotas isentas
 *    (ex.: /status para health check e /sso/{token}, que o navegador do aluno
 *    acessa e já tem seu próprio token criptografado).
 */
final class ApiKeyAuth
{
    /**
     * @param string   $apiKey          valor de API_KEY (vazio = auth desativada)
     * @param string[] $isentos         prefixos de rota liberados sem chave
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly array $isentos = ['/status', '/sso']
    ) {
    }

    public function __invoke(Request $request, Handler $handler): Response
    {
        // Preflight CORS nunca exige chave.
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $handler->handle($request);
        }

        // Auth desativada enquanto a chave não for configurada.
        if ($this->apiKey === '') {
            error_log('[RMAPI] AVISO: API_KEY nao definida — autenticacao DESATIVADA.');
            return $handler->handle($request);
        }

        // Rotas isentas (health, SSO do aluno…).
        $path = $request->getUri()->getPath();
        foreach ($this->isentos as $prefixo) {
            if ($path === $prefixo || str_starts_with($path, $prefixo . '/') || str_starts_with($path, $prefixo)) {
                return $handler->handle($request);
            }
        }

        if (!hash_equals($this->apiKey, $this->extrairChave($request))) {
            return Json::error(
                'Não autorizado. Envie o header X-API-Key com a chave da API.',
                ['detalhe' => 'API key ausente ou inválida.'],
                401
            );
        }

        return $handler->handle($request);
    }

    private function extrairChave(Request $request): string
    {
        $chave = trim($request->getHeaderLine('X-API-Key'));
        if ($chave !== '') {
            return $chave;
        }

        // Fallback: Authorization: Bearer <chave>
        $auth = $request->getHeaderLine('Authorization');
        if (stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }

        return '';
    }
}
