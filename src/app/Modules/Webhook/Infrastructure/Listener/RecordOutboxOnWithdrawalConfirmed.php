<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Listener;

use App\Modules\Webhook\Application\UseCase\RecordOutboxMessage\RecordOutboxMessageAction;
use App\Modules\Webhook\Application\UseCase\RecordOutboxMessage\RecordOutboxMessageData;
use App\Modules\Withdrawal\Domain\Event\WithdrawalConfirmed;

/**
 * Cross-module Infrastructure listener: подписан на чужой Domain event без
 * нарушения boundaries (Infrastructure → чужой Domain — разрешено;
 * Application → чужой Domain — НЕ разрешено).
 */
final readonly class RecordOutboxOnWithdrawalConfirmed
{
    public function __construct(private RecordOutboxMessageAction $action) {}

    public function handle(WithdrawalConfirmed $event): void
    {
        $this->action->handle(new RecordOutboxMessageData(
            eventName: 'withdrawal.confirmed',
            aggregateId: $event->id->value,
            payload: [
                'withdrawal_id' => $event->id->value,
                'confirmations' => $event->confirmations,
                'occurred_at' => $event->occurredAt->format(DATE_ATOM),
            ],
        ));
    }
}
