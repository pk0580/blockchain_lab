<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\ReadModel;

use App\Modules\BlockIngestion\Domain\ValueObject\Amount;

final readonly class FetchedOutput
{
    public function __construct(
        public string $toAddress,
        public Amount $amount,
    ) {}
}
