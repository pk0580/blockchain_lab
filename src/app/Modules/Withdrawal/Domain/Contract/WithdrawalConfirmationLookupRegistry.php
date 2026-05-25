<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Withdrawal\Domain\Exception\UnsupportedConfirmationLookupException;

interface WithdrawalConfirmationLookupRegistry
{
    /**
     * @throws UnsupportedConfirmationLookupException если для семейства лукапа нет
     *         (Tron — NoOp; конкретный lookup будет подключён позже).
     */
    public function for(ChainFamily $family): WithdrawalConfirmationLookup;
}
