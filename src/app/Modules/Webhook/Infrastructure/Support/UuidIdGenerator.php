<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Support;

use App\Modules\Webhook\Application\Contract\IdGenerator;
use App\Modules\Webhook\Domain\ValueObject\OutboxMessageId;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryId;
use Illuminate\Support\Str;

final readonly class UuidIdGenerator implements IdGenerator
{
    public function nextOutboxId(): OutboxMessageId
    {
        return new OutboxMessageId((string) Str::uuid());
    }

    public function nextDeliveryId(): WebhookDeliveryId
    {
        return new WebhookDeliveryId((string) Str::uuid());
    }
}
