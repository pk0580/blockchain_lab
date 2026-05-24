<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Application\UseCase\EvaluateBlockReorg;

/**
 * Входные данные приходят из обработчика BlockIngested-события в Infrastructure
 * и уже не несут в себе никаких VO «соседнего» модуля — только примитивы.
 */
final readonly class EvaluateBlockReorgData
{
    public function __construct(
        public string $chainId,
        public int $height,
        public string $hash,
        public string $parentHash,
    ) {}
}
