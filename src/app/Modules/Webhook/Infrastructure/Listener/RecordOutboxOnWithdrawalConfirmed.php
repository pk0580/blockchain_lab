<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Listener;

use App\Modules\Webhook\Application\UseCase\RecordOutboxMessage\RecordOutboxMessageAction;
use App\Modules\Webhook\Application\UseCase\RecordOutboxMessage\RecordOutboxMessageData;
use App\Modules\Withdrawal\Domain\Event\WithdrawalConfirmed;

/**
 * Withdrawal::WithdrawalConfirmed → Webhook outbox (`withdrawal.confirmed`).
 *
 * Подписанные клиенты получают уведомление, что исходящий платёж достиг
 * requiredConfirmations. Доставка через transactional outbox (at-least-once,
 * GUIDE.md, Урок 12.2).
 *
 * Cross-module Infrastructure listener: подписка на чужой Domain event
 * разрешена только в Infrastructure (Application → чужой Domain — нельзя).
 *
 * @see \GUIDE.md  Урок 12 (#урок-12--надёжность-и-наблюдаемость)
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
