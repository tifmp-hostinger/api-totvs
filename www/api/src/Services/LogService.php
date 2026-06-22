<?php

declare(strict_types=1);

namespace FMP\RMApi\Services;

use FMP\RMApi\Clients\RMSoapClient;
use Throwable;

/**
 * Log de integração gravado no próprio RM
 * (DataServer custom RMSPRJ5495296Server, tabela ZMDLOGINTEGEDUVEM).
 *
 * Tolerante a falha: um erro ao gravar o log NUNCA derruba o fluxo de
 * negócio — cai para o error_log do PHP.
 */
class LogService
{
    public function __construct(private readonly RMSoapClient $rm)
    {
    }

    public function saveLog(
        string $email,
        string $entity,
        string $offer,
        string $status,
        string $message,
        mixed $payload
    ): void {
        if (is_array($payload)) {
            $payload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $xml = <<<XML
        <PRJ5495296>
            <ZMDLOGINTEGEDUVEM>
                <ID>0</ID>
                <EMAIL>{$email}</EMAIL>
                <ENTIDADE>{$entity}</ENTIDADE>
                <CODOFERTA>{$offer}</CODOFERTA>
                <STATUS>{$status}</STATUS>
                <MENSAGEM>{$message}</MENSAGEM>
                <XML><![CDATA[{$payload}]]></XML>
            </ZMDLOGINTEGEDUVEM>
        </PRJ5495296>
        XML;

        try {
            $this->rm->saveRecord('RMSPRJ5495296Server', $xml);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[RM-API] Falha ao gravar log no RM: %s | log original: [%s/%s] %s',
                $e->getMessage(),
                $entity,
                $status,
                $message
            ));
        }
    }
}
