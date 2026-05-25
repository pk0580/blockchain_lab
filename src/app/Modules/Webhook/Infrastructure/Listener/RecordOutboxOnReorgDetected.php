<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Listener;

use App\Modules\ReorgDetection\Domain\Event\ReorgDetected;
use App\Modules\Webhook\Application\UseCase\RecordOutboxMessage\RecordOutboxMessageAction;
use App\Modules\Webhook\Application\UseCase\RecordOutboxMessage\RecordOutboxMessageData;

/**
 * Мост ReorgDetection::ReorgDetected → Webhook::Outbox.
 *
 * Часть «Реакция других модулей» из GUIDE.md, Урок 7: при реорге клиенты,
 * подписанные на `reorg.detected`, получают webhook с описанием orphaned-блока.
 * Доставка идёт через transactional outbox (GUIDE.md, Урок 12.2) — at-least-once.
 *
 * @see \GUIDE.md  Урок 7 (#урок-7--реорганизации-цепи)
 * @see \GUIDE.md  Урок 12 (#урок-12--надёжность-и-наблюдаемость)
 */
final readonly class RecordOutboxOnReorgDetected
{
    public function __construct(private RecordOutboxMessageAction $action) {}

    public function handle(ReorgDetected $event): void
    {
        $this->action->handle(new RecordOutboxMessageData(
            eventName: 'reorg.detected',
            aggregateId: $event->chainId->value.':'.$event->orphanedHeight->value,
            payload: [
                'chain_id' => $event->chainId->value,
                'orphaned_height' => $event->orphanedHeight->value,
                'depth' => $event->depth->value,
                'orphaned_transaction_count' => $event->orphanedTransactionCount,
                'occurred_at' => $event->occurredAt->format(DATE_ATOM),
            ],
        ));
    }
}
