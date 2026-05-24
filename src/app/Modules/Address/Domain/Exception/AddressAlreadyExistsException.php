<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DomainException;

final class AddressAlreadyExistsException extends DomainException
{
    public static function forFamily(ChainFamily $family, string $address): self
    {
        return new self("Address '{$address}' is already registered for family {$family->value}.");
    }
}
