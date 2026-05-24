<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainId;
use RuntimeException;

/**
 * Reorg ушёл глубже `chain.max_reorg_depth` — узел показал, что мы ошибочно
 * считали финализированными блоки, которые на самом деле были orphaned.
 * Пока ops не разрешит вручную (или TTL не истечёт), исходящие withdrawal'ы
 * для этой сети блокируются (HTTP 503).
 *
 * Это не временный сбой инфраструктуры (для него — `WithdrawalBroadcastFailedException`),
 * а аварийная политика поверх chain'а. Маппится в 503 Service Unavailable.
 */
final class ChainPausedException extends RuntimeException
{
    public static function forChain(ChainId $chainId): self
    {
        return new self(
            "Chain '{$chainId->value}' is paused for withdrawals after a deep reorg. "
            .'Wait for operator clearance.',
        );
    }
}
