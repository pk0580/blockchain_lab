<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Иммутабельный результат одной попытки доставки. Application использует:
 *
 *   - `success=true`         → markDelivered(responseStatus, now)
 *   - `retryable=true`       → reschedule (Pending+attempts++)
 *   - иначе                  → markFailed(reason)
 *
 * Логика «4xx == не retryable, 5xx == retryable, timeout/no-response == retryable»
 * собирается в `HttpWebhookDispatcher::dispatch()`.
 */
final readonly class WebhookDispatchOutcome
{
    public function __construct(
        public bool $success,
        public bool $retryable,
        public ?int $responseStatus,
        public ?string $error,
        public int $latencyMs,
    ) {
        if ($success && $retryable) {
            throw new InvalidArgumentException(
                'WebhookDispatchOutcome cannot be both success and retryable.'
            );
        }
        if ($success && ($responseStatus === null || $responseStatus < 200 || $responseStatus >= 300)) {
            throw new InvalidArgumentException(
                'WebhookDispatchOutcome::success requires 2xx response status.'
            );
        }
        if ($latencyMs < 0) {
            throw new InvalidArgumentException("latencyMs must be >= 0, got {$latencyMs}.");
        }
    }

    public static function delivered(int $responseStatus, int $latencyMs): self
    {
        return new self(true, false, $responseStatus, null, $latencyMs);
    }

    public static function retryable(?int $responseStatus, string $error, int $latencyMs): self
    {
        return new self(false, true, $responseStatus, $error, $latencyMs);
    }

    public static function failed(?int $responseStatus, string $error, int $latencyMs): self
    {
        return new self(false, false, $responseStatus, $error, $latencyMs);
    }
}
