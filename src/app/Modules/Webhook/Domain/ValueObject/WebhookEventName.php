<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Имя domain-события, которое подписчик хочет получать (`withdrawal.confirmed`,
 * `transaction.confirmed`, `reorg.detected`). Строка под snake_case + dot-separated.
 *
 * Domain-стороны (Withdrawal, Confirmation, ReorgDetection) НЕ зависят от Webhook,
 * поэтому имя события не маппится автоматически из PHP-class'а. Маппинг описан в
 * `src/config/webhook.php` (`event_map`).
 */
final readonly class WebhookEventName
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $value)) {
            throw new InvalidArgumentException(
                "WebhookEventName must be `lower.snake_case.dot.separated`, got '{$value}'."
            );
        }
        if (strlen($value) > 64) {
            throw new InvalidArgumentException("WebhookEventName too long: '{$value}'.");
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
