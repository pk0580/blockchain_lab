<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Listener;

use App\Modules\Withdrawal\Application\UseCase\ReplaceStuckWithdrawal\ReplaceStuckWithdrawalAction;
use App\Modules\Withdrawal\Application\UseCase\ReplaceStuckWithdrawal\ReplaceStuckWithdrawalData;
use App\Modules\Withdrawal\Domain\Event\WithdrawalStuck;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WithdrawalStuck → ReplaceStuckWithdrawalAction (GUIDE.md, Урок 11 «Замена»).
 *
 * Логирует, но не повторяет на сбое — повторный запуск гарантирует
 * {@see WatchStuckWithdrawalsJob}: оригинал так и остаётся Stuck, при следующем
 * тике listener дёрнут снова. Action идемпотентен через
 * `idempotency_key = "rbf:{original_id}"` (UNIQUE в `withdrawals`).
 *
 * @see \GUIDE.md  Урок 11 (#урок-11--застрявшие-транзакции-и-rbf)
 */
final readonly class ReplaceOnWithdrawalStuck
{
    public function __construct(private ReplaceStuckWithdrawalAction $action) {}

    public function handle(WithdrawalStuck $event): void
    {
        try {
            $this->action->handle(new ReplaceStuckWithdrawalData($event->id->value));
        } catch (Throwable $e) {
            Log::warning('withdrawal.replace.failed', [
                'withdrawal_id' => $event->id->value,
                'chain_id' => $event->chainId->value,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
