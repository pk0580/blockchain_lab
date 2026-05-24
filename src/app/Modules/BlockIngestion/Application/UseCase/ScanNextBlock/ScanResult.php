<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Application\UseCase\ScanNextBlock;

final readonly class ScanResult
{
    public function __construct(
        public string $chainId,
        public int $blocksScanned,
        public int $matchesDetected,
        public int $lastScannedHeight,
        public int $headHeight,
    ) {}
}
