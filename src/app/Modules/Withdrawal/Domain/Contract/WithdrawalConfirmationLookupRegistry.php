<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Withdrawal\Domain\Exception\UnsupportedConfirmationLookupException;

interface WithdrawalConfirmationLookupRegistry
{
    /**
     * @throws UnsupportedConfirmationLookupException если для семейства лукапа нет
     *         (Tron в Phase 6.3 — NoOp, конкретные тиры подключим в Phase 7+).
     */
    public function for(ChainFamily $family): WithdrawalConfirmationLookup;
}
