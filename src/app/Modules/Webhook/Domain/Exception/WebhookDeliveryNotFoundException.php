<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\Exception;

use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryId;
use RuntimeException;

final class WebhookDeliveryNotFoundException extends RuntimeException
{
    public static function byId(WebhookDeliveryId $id): self
    {
        return new self("Webhook delivery '{$id->value}' not found.");
    }
}
