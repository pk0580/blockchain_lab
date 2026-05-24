<?php

declare(strict_types=1);

namespace App\Modules\Address\Application\UseCase\GenerateAddress;

final readonly class GenerateAddressData
{
    public function __construct(
        public string $seedId,
        public string $family,
        public ?string $walletId = null,
    ) {}
}
