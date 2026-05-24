<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Rpc;

use RuntimeException;

/**
 * JSON-RPC application error (узел вернул `{"error":{code,message}}`). Содержит
 * код, который downstream-маппинг переводит в Domain-исключения (например,
 * BroadcastFailedException::rpc).
 *
 * NB: код хранится в `$rpcCode`, а не в наследуемом `$code`, потому что у
 * `Exception::$code` зафиксирована сигнатура (int, read-write), и любое
 * переопределение типа/readonly его ломает.
 */
final class RpcError extends RuntimeException
{
    public function __construct(string $message, public readonly string $rpcCode)
    {
        parent::__construct($message);
    }
}
