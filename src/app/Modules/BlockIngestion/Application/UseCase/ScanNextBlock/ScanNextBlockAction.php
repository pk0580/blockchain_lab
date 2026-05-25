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
 * Сканер цепи: один тик для одной сети.
 *
 * Алгоритм (GUIDE.md, Урок 5 «Цикл сканирования»):
 *
 *   1. Загрузить Chain и ScanCursor.
 *   2. Спросить currentHead у источника (BitcoinCoreBlockSource / EVM RPC).
 *   3. cursor.observeHead(head) — обновить наблюдение вершины.
 *   4. Пока hasPendingBlocks() и не превысили лимит за тик:
 *        a. fetchBlockAt(cursor.nextHeight())
 *        b. ingestBlock(...) — В СОБСТВЕННОЙ ТРАНЗАКЦИИ
 *   5. Если за тик не сканировали ни одного блока — отдельно сохранить
 *      курсор (зафиксировать наблюдение `lastSeenHeadHeight`).
 *
 * ⚠️ Каждый блок обрабатывается в собственной транзакции: ошибка на блоке
 * K+5 не откатывает блоки K..K+4. События диспатчатся строго после COMMIT.
 *
 * ⚠️ Не перезаписываем курсор на шаге 5, если ingestBlock уже выполнился —
 * иначе откат, сделанный ReorgDetection в afterCommit, был бы затёрт обратно
 * (GUIDE.md, Урок 7, последний абзац «Что делает Action при обнаружении reorg»).
 *
 * @see \GUIDE.md  Урок 5 (#урок-5--сканирование-цепи-и-обнаружение-поступлений)
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
        // Шаг 1 (GUIDE §5): загрузить сеть и курсор.
        $chainId = new ChainId($data->chainId);
        $chain = $this->chains->findById($chainId)
            ?? throw ChainNotFoundException::byId($chainId);

        $cursor = $this->cursors->findByChain($chainId)
            ?? throw ScanCursorMissingException::forChain($chainId);

        // Шаги 2-3 (GUIDE §5): спросить вершину сети и зафиксировать наблюдение.
        $source = $this->sources->for($chain);
        $head = $source->currentHead();
        $cursor->observeHead($head, new DateTimeImmutable());

        $scanned = 0;
        $matched = 0;
        $maxBlocks = max(1, $data->maxBlocksPerTick);

        // Шаг 4 (GUIDE §5): догон по одному блоку в своей транзакции.
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
     * Поиск «наших» поступлений в блоке (GUIDE §5 «Что значит "найти поступление"»):
     *   1. Перебрать все транзакции блока, для каждой — все outputs.
     *   2. Для каждого output взять адрес-получателя.
     *   3. Проверить через {@see AddressDirectory::isWatched()} — наш ли это адрес.
     *      Это O(1)-проверка по Redis SET, см. GUIDE §5 «Почему Redis, а не PostgreSQL».
     *   4. Убедиться, что записи о ней ещё нет (защита от повторного сканирования
     *      того же блока, например после рестарта).
     *
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
                // O(1) SISMEMBER к Redis-набору адресов семейства.
                if (! $this->directory->isWatched($chain->family, $output->toAddress)) {
                    continue;
                }
                // Идемпотентность: не плодим IncomingTransaction для уже виденной пары.
                if ($this->incomingTxs->existsForRecipient($chain->id, $tx->txHash, $output->toAddress)) {
                    continue;
                }
                $matches[] = ['tx' => $tx, 'output' => $output];
            }
        }
        return $matches;
    }
}
