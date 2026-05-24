<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\Event;

use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryId;
use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use App\Modules\Webhook\Domain\ValueObject\WebhookSubscriptionId;
use DateTimeImmutable;

final readonly class WebhookDelivered
{
    public function __construct(
        public WebhookDeliveryId $id,
        public WebhookSubscriptionId $subscriptionId,
        public WebhookEventName $eventName,
        public DateTimeImmutable $occurredAt,
    ) {}
}
