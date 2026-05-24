<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Event;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use DateTimeImmutable;

/**
 * Сигнализирует, что withdrawal провисел в `Broadcasted`/`Confirming` дольше
 * config('withdrawal.stuck_after_seconds'). `ReplaceStuckWithdrawalAction`
 * подписан на это событие и запускает RBF (BTC) или resend с тем же nonce
 * + поднятым gas (EVM).
 */
final readonly class WithdrawalStuck
{
    public function __construct(
        public WithdrawalId $id,
        public ChainId $chainId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
