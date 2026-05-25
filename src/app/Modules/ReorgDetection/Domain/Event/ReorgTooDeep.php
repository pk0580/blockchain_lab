<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Domain\Event;

use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

/**
 * Сигнализирует, что reorg затронул блок, который уже считался finalized
 * (глубже chain.max_reorg_depth). Обработчик в Withdrawal ставит сеть на
 * паузу (см. PauseChainOnReorgTooDeep).
 */
final readonly class ReorgTooDeep
{
    public function __construct(
        public ChainId $chainId,
        public BlockHeight $orphanedHeight,
        public int $observedDepth,
        public int $threshold,
        public DateTimeImmutable $occurredAt,
    ) {}
}
