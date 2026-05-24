<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ChainFamily;

interface TxBuilderRegistry
{
    /**
     * @throws \RuntimeException когда builder не зарегистрирован для family.
     */
    public function for(ChainFamily $family): TxBuilder;
}
