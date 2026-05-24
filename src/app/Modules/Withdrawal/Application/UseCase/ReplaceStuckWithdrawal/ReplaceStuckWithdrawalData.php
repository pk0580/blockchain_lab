<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\ReplaceStuckWithdrawal;

final readonly class ReplaceStuckWithdrawalData
{
    public function __construct(public string $originalWithdrawalId) {}
}
