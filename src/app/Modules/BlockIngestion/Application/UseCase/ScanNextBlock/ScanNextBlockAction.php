<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Application\UseCase\ScanNextBlock;

use App\Modules\BlockIngestion\Domain\Contract\AddressDirectory;
use App\Modules\BlockIngestion\Domain\Contract\BlockSourceFactory;
use App\Modules\BlockIngestion\Domain\Entity\Block;
use App\Modules\BlockIngestion\Domain\Entity\IncomingTransaction;
use App\Modules\BlockIngestion\Domain\Entity\ScanCursor;
use App\Modules\BlockIngestion\Domain\Exception\ScanCursorMissingException;
use App\Modules\BlockIngestion\Domain\ReadModel\FetchedBlock;
use App\Modules\BlockIngestion\Domain\ReadModel\FetchedOutput;
use App\Modules\BlockIngestion\Domain\ReadModel\FetchedTransaction;
use App\Modules\BlockIngestion\Domain\Repository\BlockRepository;
use App\Modules\BlockIngestion\Domain\Repository\IncomingTransactionRepository;
use App\Modules\BlockIngestion\Domain\Repository\ScanCursorRepository;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTransactionId;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Получает до N ожидающих блоков из сети и сохраняет совпадения. Каждый
 * блок обрабатывается внутри собственной транзакции, поэтому ошибка на блоке K+5
 * не откатывает блоки K..K+4, которые уже были успешно обработаны. События инициируются только
 * после завершения коммита каждого блока.
 */
final readonly class ScanNextBlockAction
{
    public function __construct(
        private ChainRepository $chains,
        private ScanCursorRepository $cursors,
        private BlockRepository $blocks,
        private IncomingTransactionRepository $incomingTxs,
        private BlockSourceFactory $sources,
        private AddressDirectory $directory,
        private DatabaseManager $db,
        private Dispatcher $events,
    ) {}

    public function handle(ScanNextBlockData $data): ScanResult
    {
        $chainId = new ChainId($data->chainId);
        $chain = $this->chains->findById($chainId)
            ?? throw ChainNotFoundException::byId($chainId);

        $cursor = $this->cursors->findByChain($chainId)
            ?? throw ScanCursorMissingException::forChain($chainId);

        $source = $this->sources->for($chain);
        $head = $source->currentHead();
        $cursor->observeHead($head, new DateTimeImmutable());

        $scanned = 0;
        $matched = 0;
        $maxBlocks = max(1, $data->maxBlocksPerTick);

        while ($cursor->hasPendingBlocks() && $scanned < $maxBlocks) {
            $next = $cursor->nextHeight();
            $fetched = $source->fetchBlockAt($next);
            $matched += $this->ingestBlock($chain, $cursor, $fetched);
            $scanned++;
        }

        // Сохраняем head-observation только если за тик не было ингеста: каждый
        // ingestBlock уже записал актуальный курсор внутри своей транзакции. Если
        // ReorgDetection в afterCommit-обработчике откатил cursor.last_scanned_height,
        // здесь мы НЕ перетираем его — иначе итеративный walk-back ломается.
        if ($scanned === 0) {
            $this->db->transaction(function () use ($cursor): void {
                $this->cursors->save($cursor);
            });
        }

        return new ScanResult(
            chainId: $chainId->value,
            blocksScanned: $scanned,
            matchesDetected: $matched,
            lastScannedHeight: $cursor->lastScannedHeight()->value,
            headHeight: $cursor->lastSeenHeadHeight()->value,
        );
    }

    private function ingestBlock(Chain $chain, ScanCursor $cursor, FetchedBlock $fetched): int
    {
        $now = new DateTimeImmutable();
        $matchedTxs = $this->matchingTransactions($chain, $fetched);

        $block = Block::ingest(
            chainId: $chain->id,
            height: $fetched->height,
            hash: $fetched->hash,
            parentHash: $fetched->parentHash,
            timestamp: $fetched->timestamp,
            scannedAt: $now,
        );

        $entities = [];
        foreach ($matchedTxs as $match) {
            $entities[] = IncomingTransaction::detected(
                id: new IncomingTransactionId((string) Str::uuid()),
                chainId: $chain->id,
                txHash: $match['tx']->txHash,
                blockHeight: $fetched->height,
                blockHash: $fetched->hash,
                fromAddress: $match['tx']->fromAddress,
                toAddress: $match['output']->toAddress,
                amount: $match['output']->amount,
                currency: $fetched->currency,
                detectedAt: $now,
            );
        }

        $this->db->transaction(function () use ($block, $entities, $cursor, $now): void {
            $this->blocks->save($block);
            foreach ($entities as $tx) {
                $this->incomingTxs->save($tx);
            }
            $cursor->advanceTo($block->height, $now);
            $this->cursors->save($cursor);
        });

        $pending = $block->pullPendingEvents();
        foreach ($entities as $tx) {
            $pending = [...$pending, ...$tx->pullPendingEvents()];
        }
        $this->db->afterCommit(function () use ($pending): void {
            foreach ($pending as $event) {
                $this->events->dispatch($event);
            }
        });

        return count($entities);
    }

    /**
     * @return list<array{tx: FetchedTransaction, output: FetchedOutput}>
     */
    private function matchingTransactions(Chain $chain, FetchedBlock $fetched): array
    {
        $matches = [];
        foreach ($fetched->transactions as $tx) {
            foreach ($tx->outputs as $output) {
                if ($output->toAddress === '') {
                    continue;
                }
                if (! $this->directory->isWatched($chain->family, $output->toAddress)) {
                    continue;
                }
                if ($this->incomingTxs->existsForRecipient($chain->id, $tx->txHash, $output->toAddress)) {
                    continue;
                }
                $matches[] = ['tx' => $tx, 'output' => $output];
            }
        }
        return $matches;
    }
}
