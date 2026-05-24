<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Application\UseCase\DispatchWebhookDelivery;

use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryStatus;

final readonly class DispatchWebhookDeliveryResult
{
    public function __construct(
        public WebhookDeliveryStatus $status,
        public int $attempts,
        public bool $rescheduled,
        public ?int $responseStatus,
    ) {}
}
