<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Application\UseCase\PublishOutbox;

use App\Modules\Webhook\Application\Contract\IdGenerator;
use App\Modules\Webhook\Domain\Entity\WebhookDelivery;
use App\Modules\Webhook\Domain\Repository\OutboxRepository;
use App\Modules\Webhook\Domain\Repository\WebhookDeliveryRepository;
use App\Modules\Webhook\Domain\Repository\WebhookSubscriptionRepository;
use DateTimeImmutable;
use Illuminate\Database\DatabaseManager;

/**
 * Outbox publisher (GUIDE.md, Урок 12.2, шаг 2).
 *
 * Алгоритм:
 *   1. Берёт N unpublished outbox messages.
 *   2. Для каждой находит активные subscriptions для её event_name.
 *   3. Создаёт {@see WebhookDelivery} per (message, subscription).
 *   4. markPublished($now) на сообщении.
 *   5. Реальная HTTP-отправка — отдельным job {@see DispatchDueDeliveriesJob}.
 *
 * ⚠️ Fan-out + mark-published — одна транзакция per outbox message: если
 * процесс падает между ними, повторный запуск увидит unpublished и создаст
 * deliveries заново. Дубликаты ловит UNIQUE constraint на (outbox_id, subscription_id).
 *
 * @see \GUIDE.md  Урок 12 (#урок-12--надёжность-и-наблюдаемость)
 */
final readonly class PublishOutboxAction
{
    public function __construct(
        private OutboxRepository $outbox,
        private WebhookSubscriptionRepository $subscriptions,
        private WebhookDeliveryRepository $deliveries,
        private IdGenerator $ids,
        private DatabaseManager $db,
    ) {}

    public function handle(PublishOutboxData $data): PublishOutboxResult
    {
        $messages = $this->outbox->findUnpublished($data->limit);

        $publishedIds = [];
        $deliveryIds = [];

        foreach ($messages as $message) {
            $matched = $this->subscriptions->findActiveForEvent($message->eventName);

            $createdNow = [];
            $now = new DateTimeImmutable();

            $this->db->transaction(function () use (
                $message,
                $matched,
                $now,
                &$createdNow,
            ): void {
                foreach ($matched as $subscription) {
                    $delivery = WebhookDelivery::schedule(
                        id: $this->ids->nextDeliveryId(),
                        outboxId: $message->id,
                        subscriptionId: $subscription->id,
                        eventName: $message->eventName,
                        payload: $message->payload,
                        now: $now,
                    );
                    $this->deliveries->save($delivery);
                    $createdNow[] = $delivery->id->value;
                }
                $message->markPublished($now);
                $this->outbox->save($message);
            });

            $publishedIds[] = $message->id->value;
            foreach ($createdNow as $id) {
                $deliveryIds[] = $id;
            }
        }

        return new PublishOutboxResult($publishedIds, $deliveryIds);
    }
}
