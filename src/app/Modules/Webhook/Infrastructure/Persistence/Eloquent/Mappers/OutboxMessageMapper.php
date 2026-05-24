<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Mappers;

use App\Modules\Webhook\Domain\Entity\OutboxMessage;
use App\Modules\Webhook\Domain\ValueObject\OutboxMessageId;
use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Models\OutboxMessageModel;
use DateTimeImmutable;

final class OutboxMessageMapper
{
    public function toDomain(OutboxMessageModel $row): OutboxMessage
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($row->payload)
            ? $row->payload
            : (array) json_decode((string) $row->payload, true);

        return OutboxMessage::reconstitute(
            id: new OutboxMessageId($row->id),
            eventName: new WebhookEventName($row->event_name),
            aggregateId: $row->aggregate_id,
            payload: $payload,
            createdAt: DateTimeImmutable::createFromInterface($row->created_at),
            publishedAt: $row->published_at !== null
                ? DateTimeImmutable::createFromInterface($row->published_at)
                : null,
            attempts: $row->attempts,
            lastError: $row->last_error,
        );
    }

    /**
     * @return array{
     *     id: string, event_name: string, aggregate_id: string,
     *     payload: string, created_at: \DateTimeImmutable,
     *     published_at: \DateTimeImmutable|null,
     *     attempts: int, last_error: string|null,
     * }
     */
    public function toRow(OutboxMessage $msg): array
    {
        return [
            'id' => $msg->id->value,
            'event_name' => $msg->eventName->value,
            'aggregate_id' => $msg->aggregateId,
            'payload' => (string) json_encode($msg->payload, JSON_THROW_ON_ERROR),
            'created_at' => $msg->createdAt,
            'published_at' => $msg->publishedAt(),
            'attempts' => $msg->attempts(),
            'last_error' => $msg->lastError(),
        ];
    }
}
