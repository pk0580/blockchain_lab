<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\Entity;

use App\Modules\Webhook\Domain\Event\WebhookDelivered;
use App\Modules\Webhook\Domain\Event\WebhookDeliveryFailed;
use App\Modules\Webhook\Domain\ValueObject\OutboxMessageId;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryId;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryStatus;
use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use App\Modules\Webhook\Domain\ValueObject\WebhookSubscriptionId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Один HTTP-вызов для одной подписки (subscription). Создаётся `PublishOutboxAction` после
 * разветвления (fan-out); обновляется `DispatchWebhookDeliveryAction` после каждой попытки.
 *
 * Состояния (см. {@see WebhookDeliveryStatus}):
 *   Pending → Delivered (конечное)
 *           → Failed    (конечное)
 *           → Pending   (повтор — attempt++, scheduledAt сдвигается)
 *
 * Pending → Pending — это перепланирование (re-schedule) с задержкой (backoff); история повторов хранится в
 * `attempts` + `lastError`, отдельной таблицы для попыток в 7.2 не вводим
 * (Фаза 9 добавит журнал аудита для каждой попытки).
 */
final class WebhookDelivery
{
    /** @var list<object> */
    private array $pendingEvents = [];

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        public readonly WebhookDeliveryId $id,
        public readonly OutboxMessageId $outboxId,
        public readonly WebhookSubscriptionId $subscriptionId,
        public readonly WebhookEventName $eventName,
        public readonly array $payload,
        public readonly DateTimeImmutable $createdAt,
        private WebhookDeliveryStatus $status,
        private int $attempts,
        private ?string $lastError,
        private DateTimeImmutable $scheduledAt,
        private ?DateTimeImmutable $deliveredAt,
        private ?int $lastResponseStatus,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function schedule(
        WebhookDeliveryId $id,
        OutboxMessageId $outboxId,
        WebhookSubscriptionId $subscriptionId,
        WebhookEventName $eventName,
        array $payload,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            outboxId: $outboxId,
            subscriptionId: $subscriptionId,
            eventName: $eventName,
            payload: $payload,
            createdAt: $now,
            status: WebhookDeliveryStatus::Pending,
            attempts: 0,
            lastError: null,
            scheduledAt: $now,
            deliveredAt: null,
            lastResponseStatus: null,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function reconstitute(
        WebhookDeliveryId $id,
        OutboxMessageId $outboxId,
        WebhookSubscriptionId $subscriptionId,
        WebhookEventName $eventName,
        array $payload,
        DateTimeImmutable $createdAt,
        WebhookDeliveryStatus $status,
        int $attempts,
        ?string $lastError,
        DateTimeImmutable $scheduledAt,
        ?DateTimeImmutable $deliveredAt,
        ?int $lastResponseStatus,
    ): self {
        return new self(
            id: $id,
            outboxId: $outboxId,
            subscriptionId: $subscriptionId,
            eventName: $eventName,
            payload: $payload,
            createdAt: $createdAt,
            status: $status,
            attempts: $attempts,
            lastError: $lastError,
            scheduledAt: $scheduledAt,
            deliveredAt: $deliveredAt,
            lastResponseStatus: $lastResponseStatus,
        );
    }

    public function markDelivered(int $responseStatus, DateTimeImmutable $now): void
    {
        $this->status->assertCanTransitionTo(WebhookDeliveryStatus::Delivered);
        if ($responseStatus < 200 || $responseStatus >= 300) {
            throw new InvalidArgumentException(
                "WebhookDelivery::markDelivered требует статус 2xx, получено {$responseStatus}."
            );
        }
        $this->attempts++;
        $this->status = WebhookDeliveryStatus::Delivered;
        $this->deliveredAt = $now;
        $this->lastResponseStatus = $responseStatus;
        $this->lastError = null;
        $this->pendingEvents[] = new WebhookDelivered($this->id, $this->subscriptionId, $this->eventName, $now);
    }

    public function markFailed(string $reason, ?int $responseStatus, DateTimeImmutable $now): void
    {
        $this->status->assertCanTransitionTo(WebhookDeliveryStatus::Failed);
        $this->attempts++;
        $this->status = WebhookDeliveryStatus::Failed;
        $this->lastError = $reason;
        $this->lastResponseStatus = $responseStatus;
        $this->pendingEvents[] = new WebhookDeliveryFailed(
            $this->id, $this->subscriptionId, $this->eventName, $reason, $now,
        );
    }

    public function reschedule(string $reason, ?int $responseStatus, DateTimeImmutable $nextAttemptAt, DateTimeImmutable $now): void
    {
        // Pending → Pending — единственный «неконечный» (non-terminal) переход. После Delivered/Failed нельзя.
        $this->status->assertCanTransitionTo(WebhookDeliveryStatus::Pending);
        $this->attempts++;
        $this->lastError = $reason;
        $this->lastResponseStatus = $responseStatus;
        $this->scheduledAt = $nextAttemptAt;
        // статус остаётся Pending; событий не генерируем — повторная попытка это техническая деталь, а не бизнес-факт.
    }

    public function status(): WebhookDeliveryStatus
    {
        return $this->status;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function lastResponseStatus(): ?int
    {
        return $this->lastResponseStatus;
    }

    public function scheduledAt(): DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function deliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    /**
     * @return list<object>
     */
    public function pullPendingEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];
        return $events;
    }
}
