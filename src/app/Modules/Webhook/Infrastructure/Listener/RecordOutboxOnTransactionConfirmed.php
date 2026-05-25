<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Listener;

use App\Modules\Confirmation\Domain\Event\TransactionConfirmed;
use App\Modules\Webhook\Application\UseCase\RecordOutboxMessage\RecordOutboxMessageAction;
use App\Modules\Webhook\Application\UseCase\RecordOutboxMessage\RecordOutboxMessageData;

/**
 * Confirmation::TransactionConfirmed → Webhook outbox (`transaction.confirmed`).
 *
 * Подписанные клиенты получают уведомление о том, что входящая транзакция
 * достигла requiredConfirmations. Доставка через transactional outbox
 * (GUIDE.md, Урок 12.2) — at-least-once.
 *
 * @see \GUIDE.md  Урок 12 (#урок-12--надёжность-и-наблюдаемость)
 */
final readonly class RecordOutboxOnTransactionConfirmed
{
    public function __construct(private RecordOutboxMessageAction $action) {}

    public function handle(TransactionConfirmed $event): void
    {
        $this->action->handle(new RecordOutboxMessageData(
            eventName: 'transaction.confirmed',
            aggregateId: $event->incomingTransactionId,
            payload: [
                'chain_id' => $event->chainId->value,
                'incoming_transaction_id' => $event->incomingTransactionId,
                'confirmations' => $event->confirmations,
                'occurred_at' => $event->occurredAt->format(DATE_ATOM),
            ],
        ));
    }
}
