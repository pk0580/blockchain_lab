<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Listener;

use App\Modules\Withdrawal\Application\UseCase\ReplaceStuckWithdrawal\ReplaceStuckWithdrawalAction;
use App\Modules\Withdrawal\Application\UseCase\ReplaceStuckWithdrawal\ReplaceStuckWithdrawalData;
use App\Modules\Withdrawal\Domain\Event\WithdrawalStuck;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Слушает собственный domain event `WithdrawalStuck` и инициирует replacement.
 * Логирует, но не повторяет на сбое — повторный запуск гарантирует
 * `WatchStuckWithdrawalsJob`: оригинал так и остаётся в Stuck, при следующем
 * тике listener вызовут ещё раз (action идемпотентен через
 * `idempotency_key = "rbf:{original_id}"`).
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
