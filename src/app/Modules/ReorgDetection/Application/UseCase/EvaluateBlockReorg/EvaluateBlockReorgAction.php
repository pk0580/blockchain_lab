<?php

declare(strict_types=1);

namespace App\Modules\ReorgDetection\Application\UseCase\EvaluateBlockReorg;

use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\ReorgDetection\Domain\Contract\ChainHistory;
use App\Modules\ReorgDetection\Domain\Contract\ReorgWriter;
use App\Modules\ReorgDetection\Domain\Event\ReorgDetected;
use App\Modules\ReorgDetection\Domain\Event\ReorgTooDeep;
use App\Modules\ReorgDetection\Domain\ReadModel\IncomingBlockSummary;
use App\Modules\ReorgDetection\Domain\Service\ChainComparator;
use App\Modules\ReorgDetection\Domain\ValueObject\ReorgDepth;
use App\Modules\ReorgDetection\Domain\ValueObject\ReorgKind;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;

/**
 * Срабатывает при каждом BlockIngested-событии: сверяет parent_hash нового
 * блока со stored-предшественником и, если зафиксирован reorg, выполняет
 * компенсаторный набор операций (orphan, delete, rollback) и публикует
 * ReorgDetected + при превышении max_reorg_depth — ReorgTooDeep.
 *
 * Работает идемпотентно: повторный приход того же события не запустит вторую
 * компенсацию (stored prev будет уже удалён или совпадёт с parent_hash).
 */
final readonly class EvaluateBlockReorgAction
{
    public function __construct(
        private ChainRepository $chains,
        private ChainHistory $history,
        private ReorgWriter $writer,
        private ChainComparator $comparator,
        private DatabaseManager $db,
        private Dispatcher $events,
    ) {}

    public function handle(EvaluateBlockReorgData $data): EvaluateBlockReorgResult
    {
        $chainId = new ChainId($data->chainId);
        $chain = $this->chains->findById($chainId)
            ?? throw ChainNotFoundException::byId($chainId);

        $newBlock = new IncomingBlockSummary(
            chainId: $chainId,
            height: new BlockHeight($data->height),
            hash: new BlockHash($data->hash),
            parentHash: new BlockHash($data->parentHash),
        );

        $storedPrev = $data->height > 0
            ? $this->history->findByHeight($chainId, new BlockHeight($data->height - 1))
            : null;

        $analysis = $this->comparator->analyze($newBlock, $storedPrev);

        if ($analysis->kind !== ReorgKind::Reorg) {
            return new EvaluateBlockReorgResult(
                chainId: $chainId->value,
                newHeight: $data->height,
                kind: $analysis->kind,
                orphanedHeight: null,
                orphanedTransactionCount: 0,
            );
        }

        /** @var BlockHeight $orphanedHeight */
        $orphanedHeight = $analysis->orphanedHeight;

        $orphanedCount = 0;
        $this->db->transaction(function () use ($chainId, $orphanedHeight, &$orphanedCount): void {
            $orphanedCount = $this->writer->orphanIncomingAtHeight($chainId, $orphanedHeight);
            $this->writer->deleteBlockAtHeight($chainId, $orphanedHeight);

            $rollbackTarget = new BlockHeight(max(0, $orphanedHeight->value - 1));
            $this->writer->rollbackScanCursorTo($chainId, $rollbackTarget);
        });

        $threshold = $chain->confirmationRequirement->maxReorgDepth;
        $now = new DateTimeImmutable();
        $depth = new ReorgDepth(1);

        $this->db->afterCommit(function () use (
            $chainId,
            $orphanedHeight,
            $orphanedCount,
            $depth,
            $threshold,
            $now,
        ): void {
            $this->events->dispatch(new ReorgDetected(
                chainId: $chainId,
                orphanedHeight: $orphanedHeight,
                depth: $depth,
                orphanedTransactionCount: $orphanedCount,
                occurredAt: $now,
            ));

            if ($depth->value > $threshold) {
                $this->events->dispatch(new ReorgTooDeep(
                    chainId: $chainId,
                    orphanedHeight: $orphanedHeight,
                    observedDepth: $depth->value,
                    threshold: $threshold,
                    occurredAt: $now,
                ));
            }
        });

        return new EvaluateBlockReorgResult(
            chainId: $chainId->value,
            newHeight: $data->height,
            kind: ReorgKind::Reorg,
            orphanedHeight: $orphanedHeight->value,
            orphanedTransactionCount: $orphanedCount,
        );
    }
}
