<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use FMP\RMApi\Exceptions\FluxoException;
use FMP\RMApi\Exceptions\RMException;
use FMP\RMApi\Exceptions\ValidationException;
use FMP\RMApi\Helpers\Json;
use FMP\RMApi\Services\LogService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

/* ---------- Variáveis de ambiente ----------
 * Carrega um .env (se existir) como fallback. Variáveis do EasyPanel/Docker
 * têm precedência e continuam sendo lidas via getenv() nos configs.
 */
\FMP\RMApi\Support\Env::load(__DIR__ . '/../.env');

/* ---------- Captura de erro fatal ----------
 * Erros fatais (ex.: "Allowed memory size exhausted" ao carregar o WSDL
 * gigante do RM) NÃO são pegos pelo error handler do Slim e viram um 502
 * opaco no proxy. Aqui convertemos em JSON legível (e logado no stderr).
 * OBS: um segfault real do ext-soap mata o processo e nem isto roda — se
 * mesmo assim vier o 502 HTML do EasyPanel, a causa é segfault, não memória.
 */
register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e === null) {
        return;
    }
    $fatais = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($e['type'], $fatais, true) || headers_sent()) {
        return;
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    $payload = [
        'sucesso'  => false,
        'mensagem' => 'Erro fatal no processamento (possível esgotamento de memória ou falha do SOAP ao carregar o WSDL do RM).',
    ];
    if (getenv('APP_DEBUG') === 'true') {
        $payload['fatal'] = [
            'tipo'     => $e['type'],
            'mensagem' => $e['message'],
            'local'    => $e['file'] . ':' . $e['line'],
        ];
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

/* ---------- Container ---------- */

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    'rm'  => require __DIR__ . '/../config/rm.php',
    'app' => require __DIR__ . '/../config/app.php',
]);

$containerBuilder->addDefinitions(require __DIR__ . '/../config/dependencies.php');

$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->setBasePath('');
//$app->setBasePath($container->get('app')['base']);

/* ---------- Rotas ---------- */

(require __DIR__ . '/../config/routes.php')($app);

/* ---------- CORS ---------- */

$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

/* ---------- Tratamento de erros centralizado ----------
 *
 * Política de transparência total dos erros do RM:
 *  - RMException         -> 502 + operacao, dataserver, retorno_rm (+ XMLs em debug)
 *  - FluxoException      -> 422 + etapa do fluxo + retorno_rm do RM quando houver
 *  - ValidationException -> 422 + feedback de validação
 *  - HttpNotFound        -> 404
 *  - Throwable genérico  -> 500 (mensagem real exposta; stack trace só em debug)
 */

$debug = $container->get('app')['debug'];

$errorMiddleware = $app->addErrorMiddleware($debug, true, true);

$errorMiddleware->setDefaultErrorHandler(
    function (Request $request, Throwable $e) use ($container, $debug) {

        // Loga falhas de fluxo no RM (mantendo o comportamento do legado)
        if ($e instanceof FluxoException || $e instanceof ValidationException) {
            $body = (array) $request->getParsedBody();
            try {
                $container->get(LogService::class)->saveLog(
                    (string) ($body['EMAIL'] ?? ''),
                    $e->entity,
                    (string) ($body['OFERTA'] ?? ''),
                    'ERRO',
                    $e->logMessage,
                    $e->payload
                );
            } catch (Throwable) {
                // log nunca derruba a resposta
            }
        }

        if ($e instanceof ValidationException) {
            $extra = ['detalhe' => $e->logMessage];
            if ($e->etapasConcluidas !== []) {
                $extra['etapas_concluidas'] = $e->etapasConcluidas;
            }
            return Json::error($e->userFeedback, $extra, 422);
        }

        if ($e instanceof FluxoException) {
            $extra = [
                'etapa'   => $e->entity,
                'detalhe' => $e->logMessage,
            ];

            if ($debug) {
                $extra['payload'] = $e->payload;
            }

            if ($e->etapasConcluidas !== []) {
                $extra['etapas_concluidas'] = $e->etapasConcluidas;
            }

            $rmEx = $e->rmException();
            if ($rmEx !== null) {
                $extra = array_merge($extra, $rmEx->toArray($debug));
            }

            return Json::error($e->userFeedback, $extra, 422);
        }

        if ($e instanceof RMException) {
            return Json::error($e->getMessage(), $e->toArray($debug), 502);
        }

        if ($e instanceof HttpNotFoundException) {
            return Json::error('Rota não encontrada.', [], 404);
        }

        $extra = [];
        if ($debug) {
            $extra['debug'] = [
                'tipo'  => get_class($e),
                'file'  => $e->getFile() . ':' . $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ];
        }

        return Json::error($e->getMessage(), $extra, 500);
    }
);

$app->run();
