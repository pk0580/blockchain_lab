<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Domain\Event;

use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\ReorgDetection\Domain\ValueObject\ReorgDepth;
use DateTimeImmutable;

/**
 * Эмитится после успешной компенсаторной операции: один блок старой цепи
 * orphan'ен, scan-курсор откатан, новые слушатели (Ledger) могут начинать
 * reversal-проводки.
 *
 * `orphanedHeight` — высота блока, который выпал из канона. `depth=1` —
 * фаза 5 публикует одно событие на тик; накапливать общее значение должен
 * потребитель (или будущая агрегация в NodeHealth).
 */
final readonly class ReorgDetected
{
    public function __construct(
        public ChainId $chainId,
        public BlockHeight $orphanedHeight,
        public ReorgDepth $depth,
        public int $orphanedTransactionCount,
        public DateTimeImmutable $occurredAt,
    ) {}
}
