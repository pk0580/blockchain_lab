<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Application\UseCase\EvaluateBlockReorg;

use App\Modules\ReorgDetection\Domain\ValueObject\ReorgKind;

final readonly class EvaluateBlockReorgResult
{
    public function __construct(
        public string $chainId,
        public int $newHeight,
        public ReorgKind $kind,
        public ?int $orphanedHeight,
        public int $orphanedTransactionCount,
    ) {}
}
