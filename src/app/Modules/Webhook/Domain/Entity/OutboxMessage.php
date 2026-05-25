<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\Entity;

use App\Modules\Webhook\Domain\ValueObject\OutboxMessageId;
use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Запись transactional outbox — событие, которое нужно опубликовать клиентам.
 *
 * Решает проблему dual-write (GUIDE.md, Урок 12.2):
 *  - DB::transaction(fn() => $repo->save($order));
 *    $messageBus->publish(...);  // ← процесс упал здесь — клиент не получит уведомление
 *
 * Решение: пишем сообщение в `outbox_messages` В ТОЙ ЖЕ транзакции, что и
 * бизнес-сущность. Отдельный воркер ({@see PublishOutboxAction}) находит
 * unpublished записи, фан-аутит в deliveries и помечает published.
 *
 * Гарантия: at-least-once. Получатель должен сам быть идемпотентным.
 *
 * @see \GUIDE.md  Урок 12 (#урок-12--надёжность-и-наблюдаемость)
 */
final class OutboxMessage
{
    private function __construct(
        public readonly OutboxMessageId $id,
        public readonly WebhookEventName $eventName,
        public readonly string $aggregateId,
        /** @var array<string, mixed> */
        public readonly array $payload,
        public readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $publishedAt,
        private int $attempts,
        private ?string $lastError,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function record(
        OutboxMessageId $id,
        WebhookEventName $eventName,
        string $aggregateId,
        array $payload,
        DateTimeImmutable $now,
    ): self {
        if (trim($aggregateId) === '') {
            throw new InvalidArgumentException('OutboxMessage requires a non-empty aggregateId.');
        }
        return new self(
            id: $id,
            eventName: $eventName,
            aggregateId: $aggregateId,
            payload: $payload,
            createdAt: $now,
            publishedAt: null,
            attempts: 0,
            lastError: null,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function reconstitute(
        OutboxMessageId $id,
        WebhookEventName $eventName,
        string $aggregateId,
        array $payload,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $publishedAt,
        int $attempts,
        ?string $lastError,
    ): self {
        return new self(
            id: $id,
            eventName: $eventName,
            aggregateId: $aggregateId,
            payload: $payload,
            createdAt: $createdAt,
            publishedAt: $publishedAt,
            attempts: $attempts,
            lastError: $lastError,
        );
    }

    public function markPublished(DateTimeImmutable $now): void
    {
        $this->publishedAt = $now;
        $this->lastError = null;
    }

    public function recordFailure(string $reason): void
    {
        $this->attempts++;
        $this->lastError = $reason;
    }

    public function isPublished(): bool
    {
        return $this->publishedAt !== null;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }
}
