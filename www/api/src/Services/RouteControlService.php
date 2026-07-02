<?php

declare(strict_types=1);

namespace FMP\RMApi\Services;

use FMP\RMApi\Exceptions\ValidationException;
use FMP\RMApi\Support\RouteCatalog;

/**
 * Estado (ativa/desativada) e estatísticas de uso das rotas do catálogo.
 *
 * Persistência em arquivos JSON no diretório var/ (configurável via
 * APP_VAR_DIR), com flock para tolerar os múltiplos workers do Apache:
 *  - rotas-estado.json  { "rm.save": { "ativa": false, "motivo": "...", "atualizado_em": "..." } }
 *  - rotas-stats.json   { "rm.save": { "hits": 10, "bloqueios": 2, "ultimo_acesso": "...", "ultimo_status": 200, "ultima_duracao_ms": 45 } }
 *
 * Rotas ausentes do arquivo de estado são consideradas ATIVAS (default
 * seguro: nada muda até alguém desativar pelo painel). Se o diretório não
 * for gravável, a API continua funcionando com tudo ativo — a falha aparece
 * só ao tentar salvar pelo painel.
 */
final class RouteControlService
{
    private const ARQ_ESTADO = 'rotas-estado.json';
    private const ARQ_STATS  = 'rotas-stats.json';

    public function __construct(private readonly string $varDir)
    {
    }

    /* ---------------- Estado (ativa/desativada) ---------------- */

    /**
     * @return array<string, array<string, mixed>>
     */
    public function estado(): array
    {
        return $this->ler(self::ARQ_ESTADO);
    }

    public function ativa(string $id): bool
    {
        $rota = RouteCatalog::buscar($id);
        if ($rota !== null && ($rota['protegida'] ?? false)) {
            return true;
        }
        $estado = $this->estado();
        return (bool) ($estado[$id]['ativa'] ?? true);
    }

    /**
     * Ativa/desativa uma rota do catálogo.
     *
     * @return array<string, mixed> o estado gravado
     * @throws ValidationException rota inexistente ou protegida
     */
    public function definir(string $id, bool $ativa, string $motivo = ''): array
    {
        $rota = RouteCatalog::buscar($id);
        if ($rota === null) {
            throw new ValidationException(
                "Rota '{$id}' não existe no catálogo.",
                'Rota desconhecida no painel de controle',
                'ADMIN'
            );
        }
        if (($rota['protegida'] ?? false) && !$ativa) {
            throw new ValidationException(
                "A rota '{$id}' é protegida e não pode ser desativada.",
                'Tentativa de desativar rota protegida',
                'ADMIN'
            );
        }

        return $this->atualizar(self::ARQ_ESTADO, function (array $estado) use ($id, $ativa, $motivo) {
            if ($ativa) {
                // Ativa = default: basta remover a entrada.
                unset($estado[$id]);
                $gravado = ['ativa' => true, 'motivo' => '', 'atualizado_em' => date('c')];
            } else {
                $gravado = [
                    'ativa'         => false,
                    'motivo'        => mb_substr(trim($motivo), 0, 300),
                    'atualizado_em' => date('c'),
                ];
                $estado[$id] = $gravado;
            }
            return [$estado, $gravado];
        });
    }

    /* ---------------- Estatísticas de uso ---------------- */

    /**
     * @return array<string, array<string, mixed>>
     */
    public function estatisticas(): array
    {
        return $this->ler(self::ARQ_STATS);
    }

    /**
     * Registra um acesso à rota. Nunca lança exceção: estatística jamais
     * derruba uma requisição de negócio.
     */
    public function registrar(string $id, int $status, float $duracaoMs, bool $bloqueada = false): void
    {
        try {
            $this->atualizar(self::ARQ_STATS, function (array $stats) use ($id, $status, $duracaoMs, $bloqueada) {
                $atual = $stats[$id] ?? ['hits' => 0, 'bloqueios' => 0];
                $atual['hits']              = (int) ($atual['hits'] ?? 0) + 1;
                $atual['bloqueios']         = (int) ($atual['bloqueios'] ?? 0) + ($bloqueada ? 1 : 0);
                $atual['ultimo_acesso']     = date('c');
                $atual['ultimo_status']     = $status;
                $atual['ultima_duracao_ms'] = (int) round($duracaoMs);
                $stats[$id] = $atual;
                return [$stats, $atual];
            });
        } catch (\Throwable $e) {
            error_log('[RMAPI] Falha ao registrar estatística de rota: ' . $e->getMessage());
        }
    }

    public function zerarEstatisticas(): void
    {
        $this->atualizar(self::ARQ_STATS, fn(array $stats) => [[], []]);
    }

    /* ---------------- Persistência ---------------- */

    /**
     * @return array<string, array<string, mixed>>
     */
    private function ler(string $arquivo): array
    {
        $caminho = $this->varDir . '/' . $arquivo;
        if (!is_file($caminho)) {
            return [];
        }

        $fh = @fopen($caminho, 'r');
        if ($fh === false) {
            return [];
        }
        try {
            flock($fh, LOCK_SH);
            $conteudo = stream_get_contents($fh) ?: '';
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        $dados = json_decode($conteudo, true);
        return is_array($dados) ? $dados : [];
    }

    /**
     * Lê + transforma + grava sob lock exclusivo (read-modify-write atômico).
     *
     * @param callable(array): array{0: array, 1: array} $fn recebe o conteúdo
     *        atual e devolve [novoConteudo, retorno]
     * @return array<string, mixed> o retorno do callback
     */
    private function atualizar(string $arquivo, callable $fn): array
    {
        if (!is_dir($this->varDir) && !@mkdir($this->varDir, 0775, true) && !is_dir($this->varDir)) {
            throw new \RuntimeException("Diretório de dados '{$this->varDir}' não existe e não pôde ser criado.");
        }

        $caminho = $this->varDir . '/' . $arquivo;
        $fh = @fopen($caminho, 'c+');
        if ($fh === false) {
            throw new \RuntimeException("Sem permissão de escrita em '{$caminho}'.");
        }

        try {
            flock($fh, LOCK_EX);
            $conteudo = stream_get_contents($fh) ?: '';
            $dados = json_decode($conteudo, true);
            [$novo, $retorno] = $fn(is_array($dados) ? $dados : []);

            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($novo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            fflush($fh);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }

        return $retorno;
    }
}
