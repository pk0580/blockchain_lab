<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Domain\Service;

use App\Modules\ReorgDetection\Domain\ReadModel\IncomingBlockSummary;
use App\Modules\ReorgDetection\Domain\ReadModel\ReorgAnalysis;
use App\Modules\ReorgDetection\Domain\ReadModel\StoredBlockSummary;
use InvalidArgumentException;

/**
 * Чистый сервис обнаружения реорга.
 *
 * Алгоритм (GUIDE.md, Урок 7 «Алгоритм обнаружения»):
 *
 *   вход: новый блок (height H, parent_hash P) + stored prev (наш блок на H-1)
 *
 *   если storedPrev == null         → noBaseline
 *   если storedPrev.hash == P       → cleanExtension (родитель совпал)
 *   иначе                            → reorg(orphanedHeight = H-1)
 *
 * Идея: новый блок утверждает, что его родитель — P. У нас на той же высоте
 * сохранён блок с другим хешем. Значит, наш блок — orphan, его надо вытеснить.
 *
 * ⚠️ Walk-back не делает: Action отступит ровно на один блок, и следующий тик
 * сканера снова дёрнет компаратор. Так итеративно ищется общий предок
 * (GUIDE.md, Урок 7 — последний абзац «Что делает Action»).
 *
 * @see \GUIDE.md  Урок 7 (#урок-7--реорганизации-цепи)
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
