<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Domain\ReadModel;

use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\ReorgDetection\Domain\ValueObject\ReorgKind;

/**
 * Чистый результат работы ChainComparator. `orphanedHeight` заполняется
 * только для kind=Reorg: это высота старого блока, которого больше нет в
 * канонической цепи. Для NoBaseline и CleanExtension поле остаётся null.
 */
final readonly class ReorgAnalysis
{
    public function __construct(
        public ReorgKind $kind,
        public ?BlockHeight $orphanedHeight = null,
    ) {
        if ($kind === ReorgKind::Reorg && $orphanedHeight === null) {
            throw new \InvalidArgumentException('Reorg analysis requires orphanedHeight.');
        }
        if ($kind !== ReorgKind::Reorg && $orphanedHeight !== null) {
            throw new \InvalidArgumentException("Non-reorg analysis must not carry orphanedHeight.");
        }
    }

    public static function noBaseline(): self
    {
        return new self(ReorgKind::NoBaseline);
    }

    public static function cleanExtension(): self
    {
        return new self(ReorgKind::CleanExtension);
    }

    public static function reorg(BlockHeight $orphanedHeight): self
    {
        return new self(ReorgKind::Reorg, $orphanedHeight);
    }
}
