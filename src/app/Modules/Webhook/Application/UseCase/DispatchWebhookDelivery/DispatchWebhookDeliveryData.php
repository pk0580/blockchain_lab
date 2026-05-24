<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Application\UseCase\DispatchWebhookDelivery;

final readonly class DispatchWebhookDeliveryData
{
    public function __construct(public string $deliveryId) {}
}
