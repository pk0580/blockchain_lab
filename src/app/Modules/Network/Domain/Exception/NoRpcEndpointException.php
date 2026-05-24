<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use RuntimeException;

/**
 * Поднимается, когда picker не нашёл ни одного подходящего endpoint'а.
 * Возможные причины:
 *  - Chain зарегистрирован без endpoint'а нужного kind (config error).
 *  - Все endpoint'ы помечены Unhealthy NodeHealth registry (failover degraded).
 *
 * Маппится в 503 на уровне адаптера / контроллера.
 */
final class NoRpcEndpointException extends RuntimeException
{
    public static function forChain(ChainId $chainId, RpcKind $kind, ?string $reason = null): self
    {
        $msg = "No {$kind->value} RPC endpoint available for chain '{$chainId->value}'";
        if ($reason !== null && $reason !== '') {
            $msg .= ": {$reason}";
        }
        return new self($msg.'.');
    }
}
