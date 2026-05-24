<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\Repository;

use App\Modules\Webhook\Domain\Entity\WebhookSubscription;
use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use App\Modules\Webhook\Domain\ValueObject\WebhookSubscriptionId;

interface WebhookSubscriptionRepository
{
    public function findById(WebhookSubscriptionId $id): ?WebhookSubscription;

    /**
     * @return list<WebhookSubscription>
     */
    public function findActiveForEvent(WebhookEventName $event): array;

    public function save(WebhookSubscription $subscription): void;
}
