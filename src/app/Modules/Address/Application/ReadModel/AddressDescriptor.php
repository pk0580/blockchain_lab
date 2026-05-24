<?php

declare(strict_types=1);

namespace App\Modules\Address\Application\ReadModel;

/**
 * Output DTO for address-generating use cases. Application returns this so
 * UI never needs to touch the Address aggregate or Eloquent model.
 */
final readonly class AddressDescriptor
{
    public function __construct(
        public string $id,
        public string $seedId,
        public string $family,
        public string $address,
        public string $derivationPath,
        public ?string $walletId,
    ) {}
}
