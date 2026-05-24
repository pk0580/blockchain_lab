<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Exception;

use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;
use DomainException;

final class InvalidWithdrawalStateTransitionException extends DomainException
{
    public static function between(WithdrawalStatus $from, WithdrawalStatus $to): self
    {
        return new self(
            "Cannot transition Withdrawal status from '{$from->value}' to '{$to->value}'."
        );
    }
}
