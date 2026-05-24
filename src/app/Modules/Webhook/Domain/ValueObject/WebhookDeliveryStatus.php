<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\ValueObject;

use App\Modules\Webhook\Domain\Exception\InvalidDeliveryStateTransitionException;

/**
 *  Pending → Delivered (HTTP 2xx)
 *          → Failed    (HTTP 4xx — non-retryable)
 *          → Pending   (HTTP 5xx / timeout — retry с backoff)
 *
 * После Max attempts → Failed.
 */
enum WebhookDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Delivered, self::Failed => true,
            self::Pending => false,
        };
    }

    public function assertCanTransitionTo(self $next): void
    {
        $allowed = match ($this) {
            self::Pending => [self::Pending, self::Delivered, self::Failed],
            self::Delivered, self::Failed => [],
        };

        if (! in_array($next, $allowed, strict: true)) {
            throw InvalidDeliveryStateTransitionException::between($this, $next);
        }
    }
}
