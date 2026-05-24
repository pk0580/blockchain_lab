<?php

declare(strict_types=1);

namespace App\Modules\Address\Application\UseCase\ValidateAddress;

final readonly class ValidateAddressData
{
    public function __construct(
        public string $family,
        public string $address,
    ) {}
}
