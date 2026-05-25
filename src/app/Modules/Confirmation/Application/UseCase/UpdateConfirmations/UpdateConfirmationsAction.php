<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Application\UseCase\UpdateConfirmations;

use App\Modules\Confirmation\Domain\Contract\ChainScannerHead;
use App\Modules\Confirmation\Domain\Event\TransactionConfirmed;
use App\Modules\Confirmation\Domain\Event\TransactionConfirming;
use App\Modules\Confirmation\Domain\Event\TransactionFinalized;
use App\Modules\Confirmation\Domain\Repository\PendingTransactionRepository;
use App\Modules\Confirmation\Domain\Service\ConfirmationCalculator;
use App\Modules\Confirmation\Domain\ValueObject\ConfirmationOutcome;
use App\Modules\Confirmation\Domain\ValueObject\PendingTransactionView;
use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;

/**
 * Тик подтверждений для одной сети: пересчитывает все non-Finalized входящие
 * транзакции относительно текущего скан-курсора.
 *
 * Алгоритм (GUIDE.md, Урок 6 — раздел «Кто это запускает»):
 *
 *   1. Получить lastScannedHeight сети через {@see ChainScannerHead}
 *      (кросс-модульный порт — Confirmation не зависит от BlockIngestion).
 *   2. Загрузить все pending IncomingTransaction для этой сети.
 *   3. Для каждой посчитать новый outcome через {@see ConfirmationCalculator}.
 *   4. Если статус изменился — UPDATE + диспатч события
 *      (TransactionConfirming / TransactionConfirmed / TransactionFinalized).
 *   5. Если число не изменилось — no-op, чтобы не плодить событий (идемпотентность).
 *
 * ⚠️ Confirmation никогда не создаёт новые строки (это работа BlockIngestion)
 * и никогда не уменьшает confirmations (откат при реорге — отдельный путь
 * Orphaned через модуль ReorgDetection, Урок 7).
 *
 * @see \GUIDE.md  Урок 6 (#урок-6--подтверждения-и-финализация)
 */
final readonly class UpdateConfirmationsAction
{
    public function __construct(
        private ChainRepository $chains,
        private PendingTransactionRepository $pending,
        private ChainScannerHead $scannerHead,
        private ConfirmationCalculator $calculator,
        private DatabaseManager $db,
        private Dispatcher $events,
    ) {}

    public function handle(UpdateConfirmationsData $data): UpdateConfirmationsResult
    {
        $chainId = new ChainId($data->chainId);
        $chain = $this->chains->findById($chainId)
            ?? throw ChainNotFoundException::byId($chainId);

        $lastScannedHeight = $this->scannerHead->lastScannedHeight($chainId);
        if ($lastScannedHeight === null) {
            return new UpdateConfirmationsResult($chainId->value, 0, 0, 0, 0);
        }

        $required = $chain->confirmationRequirement->requiredConfirmations;
        $maxReorgDepth = $chain->confirmationRequirement->maxReorgDepth;
        $rows = $this->pending->findPending($chainId);

        $confirming = 0;
        $confirmed = 0;
        $finalized = 0;
        /** @var list<object> $pendingEvents */
        $pendingEvents = [];

        $now = new DateTimeImmutable();

        $this->db->transaction(function () use (
            $rows,
            $lastScannedHeight,
            $required,
            $maxReorgDepth,
            $now,
            &$confirming,
            &$confirmed,
            &$finalized,
            &$pendingEvents,
        ): void {
            foreach ($rows as $row) {
                $step = $this->calculator->compute(
                    txBlockHeight: $row->blockHeight,
                    lastScannedHeight: $lastScannedHeight,
                    requiredConfirmations: $required,
                    maxReorgDepth: $maxReorgDepth,
                );

                $statusChanged = $row->status !== $step->outcome;
                $countChanged = $row->confirmations !== $step->confirmations;

                if (! $statusChanged && ! $countChanged) {
                    continue;
                }

                $this->pending->applyOutcome($row->id, $step->confirmations, $step->outcome);

                if (! $statusChanged) {
                    continue;
                }

                $event = $this->makeEvent($row, $step->outcome, $step->confirmations, $now);
                if ($event !== null) {
                    $pendingEvents[] = $event;
                }

                match ($step->outcome) {
                    ConfirmationOutcome::Confirming => $confirming++,
                    ConfirmationOutcome::Confirmed => $confirmed++,
                    ConfirmationOutcome::Finalized => $finalized++,
                    default => null,
                };
            }
        });

        $this->db->afterCommit(function () use ($pendingEvents): void {
            foreach ($pendingEvents as $event) {
                $this->events->dispatch($event);
            }
        });

        return new UpdateConfirmationsResult(
            chainId: $chainId->value,
            rowsExamined: count($rows),
            rowsConfirming: $confirming,
            rowsConfirmed: $confirmed,
            rowsFinalized: $finalized,
        );
    }

    private function makeEvent(
        PendingTransactionView $row,
        ConfirmationOutcome $outcome,
        int $confirmations,
        DateTimeImmutable $now,
    ): ?object {
        return match ($outcome) {
            ConfirmationOutcome::Confirming => new TransactionConfirming(
                incomingTransactionId: $row->id,
                chainId: $row->chainId,
                confirmations: $confirmations,
                occurredAt: $now,
            ),
            ConfirmationOutcome::Confirmed => new TransactionConfirmed(
                incomingTransactionId: $row->id,
                chainId: $row->chainId,
                confirmations: $confirmations,
                occurredAt: $now,
            ),
            ConfirmationOutcome::Finalized => new TransactionFinalized(
                incomingTransactionId: $row->id,
                chainId: $row->chainId,
                confirmations: $confirmations,
                occurredAt: $now,
            ),
            ConfirmationOutcome::Detected, ConfirmationOutcome::Orphaned => null,
        };
    }
}
