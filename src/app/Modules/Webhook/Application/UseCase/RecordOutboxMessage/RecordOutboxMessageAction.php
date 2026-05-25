<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Application\UseCase\RecordOutboxMessage;

use App\Modules\Webhook\Application\Contract\IdGenerator;
use App\Modules\Webhook\Domain\Entity\OutboxMessage;
use App\Modules\Webhook\Domain\Repository\OutboxRepository;
use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use DateTimeImmutable;

/**
 * Записывает domain event в outbox table. Вызывается из Infrastructure listener'ов
 * чужих модулей (например, `RecordOutboxOnWithdrawalConfirmed`).
 *
 * **Важно:** должен вызываться внутри транзакции, которая записывала aggregate —
 * это критерий transactional outbox pattern (см. `.claude/rules/advanced_patterns.md`).
 * Listener'ы из других модулей подписываются через `afterCommit` ИЛИ в том же
 * transaction scope. Сейчас listener подписан на `WithdrawalConfirmed` (которое
 * уже dispatched после COMMIT'а в Withdrawal), поэтому пишет в свою собственную
 * транзакцию — это «approximate» outbox: окно между COMMIT'ом Withdrawal и
 * записью outbox существует, но мало, и upstream-listener идемпотентен по
 * `aggregateId`. Полноценный transactional outbox с записью inside-the-
 * source-transaction — потенциальное усиление.
 */
final readonly class RecordOutboxMessageAction
{
    public function __construct(
        private OutboxRepository $outbox,
        private IdGenerator $ids,
    ) {}

    public function handle(RecordOutboxMessageData $data): string
    {
        $message = OutboxMessage::record(
            id: $this->ids->nextOutboxId(),
            eventName: new WebhookEventName($data->eventName),
            aggregateId: $data->aggregateId,
            payload: $data->payload,
            now: new DateTimeImmutable(),
        );
        $this->outbox->save($message);

        return $message->id->value;
    }
}
