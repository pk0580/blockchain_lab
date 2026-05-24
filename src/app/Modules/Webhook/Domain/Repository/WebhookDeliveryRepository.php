<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\Repository;

use App\Modules\Webhook\Domain\Entity\WebhookDelivery;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryId;
use DateTimeImmutable;

interface WebhookDeliveryRepository
{
    public function findById(WebhookDeliveryId $id): ?WebhookDelivery;

    /**
     * Pending deliveries готовые к попытке (scheduledAt <= now).
     *
     * @return list<WebhookDelivery>
     */
    public function findDueForDispatch(DateTimeImmutable $now, int $limit): array;

    public function save(WebhookDelivery $delivery): void;
}
