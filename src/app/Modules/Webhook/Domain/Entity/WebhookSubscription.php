<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\Entity;

use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use App\Modules\Webhook\Domain\ValueObject\WebhookSecret;
use App\Modules\Webhook\Domain\ValueObject\WebhookSubscriptionId;
use App\Modules\Webhook\Domain\ValueObject\WebhookUrl;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Подписка интегратора: «куда + чем подписывать + на какие события». Сейчас
 * наполняется только из `config('webhook.subscriptions')`; admin UI + CRUD
 * endpoint'ы пока не реализованы. Поэтому у нас нет конструкторов `enable()` /
 * `disable()` или mutator-методов; subscription иммутабельна с момента создания.
 */
final readonly class WebhookSubscription
{
    /**
     * @param list<WebhookEventName> $events
     */
    public function __construct(
        public WebhookSubscriptionId $id,
        public WebhookUrl $url,
        public WebhookSecret $secret,
        public array $events,
        public bool $active,
        public DateTimeImmutable $createdAt,
    ) {
        if ($events === []) {
            throw new InvalidArgumentException(
                "Subscription '{$id->value}' must subscribe to at least one event."
            );
        }
        // Тип элементов гарантируется PHPDoc + Mapper'ом; runtime-проверка лишняя.
    }

    public function matches(WebhookEventName $event): bool
    {
        if (! $this->active) {
            return false;
        }
        foreach ($this->events as $subscribed) {
            if ($subscribed->equals($event)) {
                return true;
            }
        }
        return false;
    }
}
