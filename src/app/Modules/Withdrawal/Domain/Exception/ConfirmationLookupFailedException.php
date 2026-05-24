<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use RuntimeException;
use Throwable;

/**
 * RPC/транспортный сбой при опросе узла «сколько подтверждений у tx_hash».
 * Job ловит это исключение и пропускает запись — она будет переопрошена на
 * следующем тике (через 30 сек). Запись не помечается Failed только из-за
 * того, что узел временно недоступен.
 */
final class ConfirmationLookupFailedException extends RuntimeException
{
    public static function for(ChainId $chainId, TxHash $txHash, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            "Confirmation lookup failed for chain '{$chainId->value}' tx '{$txHash->value}': {$reason}",
            0,
            $previous,
        );
    }
}
