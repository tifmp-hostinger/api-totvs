<?php

declare(strict_types=1);

namespace FMP\RMApi\Controllers;

use FMP\RMApi\Helpers\Json;
use FMP\RMApi\Services\RouteControlService;
use FMP\RMApi\Support\RouteCatalog;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Painel de controle das rotas da API (consumido por public/admin.html).
 *
 * Todas as rotas /admin/* exigem a API key (não estão na lista de isentos
 * do ApiKeyAuth) e são protegidas contra desativação no RouteCatalog.
 */
final class AdminRotasController
{
    public function __construct(private readonly RouteControlService $controle)
    {
    }

    /** GET /admin/rotas — catálogo + estado + estatísticas */
    public function listar(Request $request, Response $response): Response
    {
        $estado = $this->controle->estado();
        $stats  = $this->controle->estatisticas();

        $rotas = [];
        foreach (RouteCatalog::todas() as $rota) {
            $id = $rota['id'];
            $protegida = (bool) ($rota['protegida'] ?? false);
            $entradaEstado = $estado[$id] ?? [];

            $rotas[] = [
                'id'            => $id,
                'metodo'        => $rota['metodo'],
                'padrao'        => $rota['padrao'],
                'grupo'         => $rota['grupo'],
                'descricao'     => $rota['descricao'],
                'protegida'     => $protegida,
                'ativa'         => $protegida || (bool) ($entradaEstado['ativa'] ?? true),
                'motivo'        => (string) ($entradaEstado['motivo'] ?? ''),
                'atualizado_em' => (string) ($entradaEstado['atualizado_em'] ?? ''),
                'exemplo'       => $rota['exemplo'] ?? null,
                'estatisticas'  => $stats[$id] ?? null,
            ];
        }

        $desativadas = count(array_filter($rotas, fn(array $r) => !$r['ativa']));

        return Json::success('Catálogo de rotas obtido com sucesso.', [
            'total'       => count($rotas),
            'ativas'      => count($rotas) - $desativadas,
            'desativadas' => $desativadas,
            'rotas'       => $rotas,
        ]);
    }

    /** PATCH /admin/rotas/{id} — body: { "ativa": bool, "motivo": "..." } */
    public function alterar(Request $request, Response $response, array $args = []): Response
    {
        $body = (array) $request->getParsedBody();

        if (!array_key_exists('ativa', $body)) {
            return Json::error("Informe o campo 'ativa' (true/false) no corpo da requisição.", [], 422);
        }

        $id     = (string) ($args['id'] ?? '');
        $ativa  = filter_var($body['ativa'], FILTER_VALIDATE_BOOL);
        $motivo = (string) ($body['motivo'] ?? '');

        $gravado = $this->controle->definir($id, $ativa, $motivo);

        return Json::success(
            $ativa ? "Rota '{$id}' ativada." : "Rota '{$id}' desativada.",
            ['id' => $id] + $gravado
        );
    }

    /** POST /admin/rotas/lote — body: { "ativa": bool, "rotas": ["id1",...], "motivo": "..." } */
    public function alterarLote(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();

        if (!array_key_exists('ativa', $body) || !is_array($body['rotas'] ?? null) || $body['rotas'] === []) {
            return Json::error(
                "Informe 'ativa' (true/false) e 'rotas' (lista de ids) no corpo da requisição.",
                [],
                422
            );
        }

        $ativa  = filter_var($body['ativa'], FILTER_VALIDATE_BOOL);
        $motivo = (string) ($body['motivo'] ?? '');

        $resultado = [];
        foreach ($body['rotas'] as $id) {
            $id = (string) $id;
            try {
                $this->controle->definir($id, $ativa, $motivo);
                $resultado[$id] = 'OK';
            } catch (\Throwable $e) {
                $resultado[$id] = 'ERRO: ' . $e->getMessage();
            }
        }

        $ok = count(array_filter($resultado, fn(string $r) => $r === 'OK'));

        return Json::success(
            sprintf('%d de %d rota(s) %s.', $ok, count($resultado), $ativa ? 'ativada(s)' : 'desativada(s)'),
            ['ativa' => $ativa, 'resultado' => $resultado]
        );
    }

    /** POST /admin/rotas/estatisticas/zerar */
    public function zerarEstatisticas(Request $request, Response $response): Response
    {
        $this->controle->zerarEstatisticas();

        return Json::success('Estatísticas de uso zeradas.');
    }
}
