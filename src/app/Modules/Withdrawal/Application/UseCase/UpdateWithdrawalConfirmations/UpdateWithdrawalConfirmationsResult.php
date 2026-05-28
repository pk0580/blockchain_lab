<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\UpdateWithdrawalConfirmations;

use App\Modules\Withdrawal\Domain\Entity\Withdrawal;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;

/**
 * Что polling-job увидел в этом тике. Используется тестами и метриками;
 * сам job эти данные не интерпретирует.
 */
final readonly class UpdateWithdrawalConfirmationsResult
{
    public function __construct(
        public WithdrawalStatus $status,
        public int $confirmations,
        public bool $changed,
        public bool $dropped,
    ) {}

    public static function noChange(Withdrawal $withdrawal): self
    {
        return new self(
            status: $withdrawal->status(),
            confirmations: $withdrawal->confirmations(),
            changed: false,
            dropped: false,
        );
    }

    public static function dropped(Withdrawal $withdrawal): self
    {
        return new self(
            status: $withdrawal->status(),
            confirmations: $withdrawal->confirmations(),
            changed: false,
            dropped: true,
        );
    }
}
