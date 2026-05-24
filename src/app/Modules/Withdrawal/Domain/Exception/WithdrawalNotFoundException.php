<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Exception;

use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use RuntimeException;

final class WithdrawalNotFoundException extends RuntimeException
{
    public static function byId(WithdrawalId $id): self
    {
        return new self("Withdrawal '{$id->value}' not found.");
    }

    public static function byIdempotencyKey(IdempotencyKey $key): self
    {
        return new self("Withdrawal with Idempotency-Key '{$key->value}' not found.");
    }
}
