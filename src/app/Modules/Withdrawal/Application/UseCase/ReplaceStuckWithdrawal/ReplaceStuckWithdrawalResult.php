<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\ReplaceStuckWithdrawal;

final readonly class ReplaceStuckWithdrawalResult
{
    public function __construct(
        public string $originalWithdrawalId,
        public string $replacementWithdrawalId,
        public string $replacementTxHash,
    ) {}
}
