<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Application\UseCase\ScanNextBlock;

final readonly class ScanNextBlockData
{
    public function __construct(
        public string $chainId,
        public int $maxBlocksPerTick = 25,
    ) {}
}
