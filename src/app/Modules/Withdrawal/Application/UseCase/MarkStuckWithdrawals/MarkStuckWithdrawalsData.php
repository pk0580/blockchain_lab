<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\MarkStuckWithdrawals;

final readonly class MarkStuckWithdrawalsData
{
    public function __construct(
        public string $chainId,
        public int $stuckAfterSeconds,
        public int $limit = 50,
    ) {}
}
