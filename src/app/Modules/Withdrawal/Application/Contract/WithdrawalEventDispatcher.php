<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\Contract;

interface WithdrawalEventDispatcher
{
    public function dispatch(object $event): void;
}
