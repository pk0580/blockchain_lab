<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DomainException;

final class WalletOwnershipMissingException extends DomainException
{
    public static function forAddress(ChainFamily $family, string $address): self
    {
        return new self(
            "No wallet attached to address '{$address}' in family '{$family->value}'."
        );
    }
}
