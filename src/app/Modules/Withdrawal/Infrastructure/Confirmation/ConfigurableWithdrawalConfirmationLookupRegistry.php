<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Confirmation;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Withdrawal\Domain\Contract\WithdrawalConfirmationLookup;
use App\Modules\Withdrawal\Domain\Contract\WithdrawalConfirmationLookupRegistry;
use App\Modules\Withdrawal\Domain\Exception\UnsupportedConfirmationLookupException;

final readonly class ConfigurableWithdrawalConfirmationLookupRegistry implements WithdrawalConfirmationLookupRegistry
{
    /**
     * @param array<string, WithdrawalConfirmationLookup> $byFamily
     */
    public function __construct(private array $byFamily) {}

    public function for(ChainFamily $family): WithdrawalConfirmationLookup
    {
        return $this->byFamily[$family->value]
            ?? throw UnsupportedConfirmationLookupException::forFamily($family);
    }
}
