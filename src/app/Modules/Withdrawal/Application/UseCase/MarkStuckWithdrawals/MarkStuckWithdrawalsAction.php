<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\MarkStuckWithdrawals;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Withdrawal\Application\Contract\WithdrawalEventDispatcher;
use App\Modules\Withdrawal\Domain\Repository\WithdrawalRepository;
use DateTimeImmutable;
use Illuminate\Database\DatabaseManager;

/**
 * Помечает Broadcasted-записи как Stuck, если они «висят» дольше TTL
 * (`config('withdrawal.stuck_after_seconds')`). После пометки эмитится
 * `WithdrawalStuck`; листенер `ReplaceStuckWithdrawal` подхватит и инициирует
 * RBF (BTC) / resend (EVM).
 *
 * Каждая запись помечается в собственной транзакции, чтобы сбой на одной не
 * заблокировал прогресс по остальным.
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
