<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\Exception;

use App\Modules\Webhook\Domain\ValueObject\WebhookSubscriptionId;
use RuntimeException;

final class WebhookSubscriptionNotFoundException extends RuntimeException
{
    public static function byId(WebhookSubscriptionId $id): self
    {
        return new self("Webhook subscription '{$id->value}' not found.");
    }
}
