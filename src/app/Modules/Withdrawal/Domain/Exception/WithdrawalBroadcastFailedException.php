<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Exception;

use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use RuntimeException;
use Throwable;

final class WithdrawalBroadcastFailedException extends RuntimeException
{
    public static function from(WithdrawalId $id, string $reason, ?Throwable $previous = null): self
    {
        return new self("Withdrawal '{$id->value}' broadcast failed: {$reason}", 0, $previous);
    }
}
