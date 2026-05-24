<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\Contract;

use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;

interface WithdrawalIdGenerator
{
    public function next(): WithdrawalId;
}
