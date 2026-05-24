<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\Address;

interface AddressValidator
{
    public function isValid(Address $address): bool;
}
