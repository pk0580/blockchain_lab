<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Support;

use App\Modules\Withdrawal\Application\Contract\WithdrawalIdGenerator;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use Illuminate\Support\Str;

final readonly class UuidWithdrawalIdGenerator implements WithdrawalIdGenerator
{
    public function next(): WithdrawalId
    {
        return new WithdrawalId(Str::uuid()->toString());
    }
}
