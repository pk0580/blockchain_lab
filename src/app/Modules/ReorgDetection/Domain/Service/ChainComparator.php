<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Domain\Service;

use App\Modules\ReorgDetection\Domain\ReadModel\IncomingBlockSummary;
use App\Modules\ReorgDetection\Domain\ReadModel\ReorgAnalysis;
use App\Modules\ReorgDetection\Domain\ReadModel\StoredBlockSummary;
use InvalidArgumentException;

/**
 * Чистый сервис: получает новый блок и stored-предшественника (если есть),
 * возвращает решение — нет базовой точки, чистое продолжение, или reorg.
 *
 * При reorg `orphanedHeight = newBlock.height - 1` — тот блок старой цепи,
 * который нужно вытеснить. Walk back не делает: обработчик откатит cursor на
 * один шаг назад и попросит BlockIngestion ре-сканировать; следующий тик
 * либо завершится CleanExtension, либо снова даст Reorg на меньшей высоте.
 */
final class ChainComparator
{
    public function analyze(
        IncomingBlockSummary $newBlock,
        ?StoredBlockSummary $storedPrev,
    ): ReorgAnalysis {
        if ($storedPrev === null) {
            return ReorgAnalysis::noBaseline();
        }

        if (! $storedPrev->chainId->equals($newBlock->chainId)) {
            throw new InvalidArgumentException(
                "ChainComparator received mismatched chains: "
                ."new={$newBlock->chainId->value}, stored={$storedPrev->chainId->value}."
            );
        }

        $expectedPrevHeight = $newBlock->height->value - 1;
        if ($storedPrev->height->value !== $expectedPrevHeight) {
            throw new InvalidArgumentException(
                "ChainComparator expected stored prev at height {$expectedPrevHeight}, "
                ."got {$storedPrev->height->value}."
            );
        }

        return $storedPrev->hash->equals($newBlock->parentHash)
            ? ReorgAnalysis::cleanExtension()
            : ReorgAnalysis::reorg($storedPrev->height);
    }
}
