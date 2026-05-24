<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Application\Contract;

use App\Modules\Webhook\Domain\ValueObject\OutboxMessageId;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryId;

/**
 * Узкий port для генерации UUID. Application не зависит от Ramsey или
 * Illuminate\Support\Str — Infrastructure подкидывает реализацию через DI.
 */
interface IdGenerator
{
    public function nextOutboxId(): OutboxMessageId;

    public function nextDeliveryId(): WebhookDeliveryId;
}
