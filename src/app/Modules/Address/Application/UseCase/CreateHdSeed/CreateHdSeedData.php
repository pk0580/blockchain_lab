<?php

declare(strict_types=1);

namespace App\Modules\Address\Application\UseCase\CreateHdSeed;

final readonly class CreateHdSeedData
{
    public function __construct(
        public string $reference,
        public ?string $family = null,
        public ?string $importMnemonic = null,
    ) {}
}
