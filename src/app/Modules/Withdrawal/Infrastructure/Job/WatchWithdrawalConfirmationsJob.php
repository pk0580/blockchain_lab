<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Job;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Withdrawal\Application\UseCase\UpdateWithdrawalConfirmations\UpdateWithdrawalConfirmationsAction;
use App\Modules\Withdrawal\Application\UseCase\UpdateWithdrawalConfirmations\UpdateWithdrawalConfirmationsData;
use App\Modules\Withdrawal\Domain\Exception\ConfirmationLookupFailedException;
use App\Modules\Withdrawal\Domain\Repository\WithdrawalRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Per-chain tick. На каждое срабатывание планировщик пушит по одной
 * `WatchWithdrawalConfirmationsJob` на каждую активную сеть. Внутри:
 * — берём до $limit активных withdrawals;
 * — на каждом вызываем `UpdateWithdrawalConfirmationsAction` (она сама внутри
 *   решает: остаться Broadcasted, перейти в Confirming или Confirmed).
 *
 * Лукап одной записи может уйти в timeout/недоступность узла — это нормальная
 * штатная ситуация. Ловим `ConfirmationLookupFailedException` и пропускаем,
 * чтобы тик не падал целиком; следующий тик переопросит запись.
 */
final class WatchWithdrawalConfirmationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    /**
     * @param int $limit максимум withdrawals, опрашиваемых за один тик.
     */
    public function __construct(
        public readonly string $chainId,
        public readonly int $limit = 100,
    ) {
        $this->onQueue("confirmations.{$chainId}");
    }

    public function handle(
        WithdrawalRepository $repo,
        UpdateWithdrawalConfirmationsAction $action,
    ): void {
        $rows = $repo->findActiveByChain(new ChainId($this->chainId), $this->limit);

        foreach ($rows as $withdrawal) {
            try {
                $action->handle(new UpdateWithdrawalConfirmationsData($withdrawal->id->value));
            } catch (ConfirmationLookupFailedException $e) {
                Log::warning('withdrawal.confirmation.lookup_failed', [
                    'chain_id' => $this->chainId,
                    'withdrawal_id' => $withdrawal->id->value,
                    'reason' => $e->getMessage(),
                ]);
            }
        }
    }
}
