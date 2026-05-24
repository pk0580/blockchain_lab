<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\Event;

use App\Modules\Address\Domain\ValueObject\AddressId;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DateTimeImmutable;

final readonly class AddressGenerated
{
    public function __construct(
        public AddressId $addressId,
        public HdSeedId $seedId,
        public ChainFamily $family,
        public string $address,
        public string $derivationPath,
        public DateTimeImmutable $occurredAt,
    ) {}
}
