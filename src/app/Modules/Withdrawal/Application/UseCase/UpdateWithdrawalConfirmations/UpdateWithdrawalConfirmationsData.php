<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\UpdateWithdrawalConfirmations;

final readonly class UpdateWithdrawalConfirmationsData
{
    public function __construct(public string $withdrawalId) {}
}
