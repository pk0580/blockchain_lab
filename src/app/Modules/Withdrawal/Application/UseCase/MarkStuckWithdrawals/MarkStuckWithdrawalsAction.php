<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\MarkStuckWithdrawals;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Withdrawal\Application\Contract\WithdrawalEventDispatcher;
use App\Modules\Withdrawal\Domain\Repository\WithdrawalRepository;
use DateTimeImmutable;
use Illuminate\Database\DatabaseManager;

/**
 * Помечает Broadcasted-withdrawals как Stuck, если они висят дольше TTL.
 *
 * Зачем — GUIDE.md, Урок 11 «Как мы это ловим»:
 *   - find all Broadcasted, у которых broadcastAt < now − stuckAfterSeconds;
 *   - для каждой markAsStuck($now) в своей транзакции;
 *   - поднять WithdrawalStuck → его слушает {@see ReplaceOnWithdrawalStuck}
 *     и инициирует RBF (BTC) / resend (EVM).
 *
 * Каждая запись помечается в собственной транзакции: сбой на одной не
 * блокирует прогресс по остальным.
 *
 * @see \GUIDE.md  Урок 11 (#урок-11--застрявшие-транзакции-и-rbf)
 */
final readonly class MarkStuckWithdrawalsAction
{
    public function __construct(
        private WithdrawalRepository $repo,
        private WithdrawalEventDispatcher $events,
        private DatabaseManager $db,
    ) {}

    public function handle(MarkStuckWithdrawalsData $data): MarkStuckWithdrawalsResult
    {
        if ($data->stuckAfterSeconds < 1) {
            return new MarkStuckWithdrawalsResult([]);
        }

        $now = new DateTimeImmutable();
        $threshold = $now->modify("-{$data->stuckAfterSeconds} seconds");

        $candidates = $this->repo->findStuckCandidates(
            new ChainId($data->chainId),
            $threshold,
            $data->limit,
        );

        $marked = [];
        foreach ($candidates as $withdrawal) {
            $events = [];
            $this->db->transaction(function () use ($withdrawal, $now, &$events): void {
                $withdrawal->markAsStuck($now);
                $this->repo->save($withdrawal);
                $events = $withdrawal->pullPendingEvents();
            });

            foreach ($events as $event) {
                $this->events->dispatch($event);
            }
            $marked[] = $withdrawal->id->value;
        }

        return new MarkStuckWithdrawalsResult($marked);
    }
}
