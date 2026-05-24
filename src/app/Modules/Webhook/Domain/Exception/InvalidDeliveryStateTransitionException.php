<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\Exception;

use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryStatus;
use DomainException;

final class InvalidDeliveryStateTransitionException extends DomainException
{
    public static function between(WebhookDeliveryStatus $from, WebhookDeliveryStatus $to): self
    {
        return new self("WebhookDelivery cannot transition from {$from->value} to {$to->value}.");
    }
}
