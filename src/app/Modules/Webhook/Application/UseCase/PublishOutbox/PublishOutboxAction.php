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
 * Берёт N unpublished outbox messages, для каждой fan-out'ит в matching
 * subscriptions: создаёт `WebhookDelivery` per (message, subscription), сохраняет.
 * После успешного fan-out помечает outbox message published.
 *
 * Fan-out + mark-published — одна транзакция per outbox message: если процесс
 * падает между ними, повторный запуск увидит unpublished и создаст delivery'и
 * заново — duplicate prevention лежит на UNIQUE constraint (outbox_id, subscription_id).
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
