<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\Contract;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Withdrawal\Application\UseCase\RequestWithdrawal\HotWalletDescriptor;

interface HotWalletResolver
{
    /**
     * @throws \RuntimeException когда hot wallet для сети не сконфигурирован.
     */
    public function resolve(Chain $chain): HotWalletDescriptor;
}
