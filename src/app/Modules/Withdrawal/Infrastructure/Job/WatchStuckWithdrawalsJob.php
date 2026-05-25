<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Job;

use App\Modules\Withdrawal\Application\UseCase\MarkStuckWithdrawals\MarkStuckWithdrawalsAction;
use App\Modules\Withdrawal\Application\UseCase\MarkStuckWithdrawals\MarkStuckWithdrawalsData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Per-chain тик для пометки stuck-withdrawals.
 *
 * Очередь — `withdrawals.{chainId}` (bulkhead: один зависший Bitcoin не валит
 * EVM-обработку, см. GUIDE.md, Урок 12.5).
 *
 * TTL `withdrawal.stuck_after_seconds` читается из конфига внутри handle(),
 * чтобы планировщик можно было пересобрать без рекомпиляции job'а.
 *
 * @see \GUIDE.md  Урок 11 (#урок-11--застрявшие-транзакции-и-rbf)
 */
final class WatchStuckWithdrawalsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly string $chainId)
    {
        $this->onQueue("withdrawals.{$chainId}");
    }

    public function handle(
        MarkStuckWithdrawalsAction $action,
        ConfigRepository $config,
    ): void {
        $ttl = (int) $config->get('withdrawal.stuck_after_seconds', 900);
        $action->handle(new MarkStuckWithdrawalsData(
            chainId: $this->chainId,
            stuckAfterSeconds: $ttl,
        ));
    }
}
