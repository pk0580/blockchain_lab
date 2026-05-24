<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Mappers;

use App\Modules\Webhook\Domain\Entity\WebhookDelivery;
use App\Modules\Webhook\Domain\ValueObject\OutboxMessageId;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryId;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryStatus;
use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use App\Modules\Webhook\Domain\ValueObject\WebhookSubscriptionId;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Models\WebhookDeliveryModel;
use DateTimeImmutable;

final class WebhookDeliveryMapper
{
    public function toDomain(WebhookDeliveryModel $row): WebhookDelivery
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($row->payload)
            ? $row->payload
            : (array) json_decode((string) $row->payload, true);

        return WebhookDelivery::reconstitute(
            id: new WebhookDeliveryId($row->id),
            outboxId: new OutboxMessageId($row->outbox_id),
            subscriptionId: new WebhookSubscriptionId($row->subscription_id),
            eventName: new WebhookEventName($row->event_name),
            payload: $payload,
            createdAt: DateTimeImmutable::createFromInterface($row->created_at),
            status: WebhookDeliveryStatus::from($row->status),
            attempts: $row->attempts,
            lastError: $row->last_error,
            scheduledAt: DateTimeImmutable::createFromInterface($row->scheduled_at),
            deliveredAt: $row->delivered_at !== null
                ? DateTimeImmutable::createFromInterface($row->delivered_at)
                : null,
            lastResponseStatus: $row->last_response_status,
        );
    }

    /**
     * @return array{
     *     id: string, outbox_id: string, subscription_id: string,
     *     event_name: string, payload: string, status: string, attempts: int,
     *     last_error: string|null, last_response_status: int|null,
     *     created_at: \DateTimeImmutable, scheduled_at: \DateTimeImmutable,
     *     delivered_at: \DateTimeImmutable|null,
     * }
     */
    public function toRow(WebhookDelivery $d): array
    {
        return [
            'id' => $d->id->value,
            'outbox_id' => $d->outboxId->value,
            'subscription_id' => $d->subscriptionId->value,
            'event_name' => $d->eventName->value,
            'payload' => (string) json_encode($d->payload, JSON_THROW_ON_ERROR),
            'status' => $d->status()->value,
            'attempts' => $d->attempts(),
            'last_error' => $d->lastError(),
            'last_response_status' => $d->lastResponseStatus(),
            'created_at' => $d->createdAt,
            'scheduled_at' => $d->scheduledAt(),
            'delivered_at' => $d->deliveredAt(),
        ];
    }
}
