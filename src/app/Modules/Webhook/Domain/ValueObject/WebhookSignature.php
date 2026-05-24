<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\ValueObject;

use InvalidArgumentException;

/**
 * HMAC-SHA256 подпись (Stripe-style):
 *   sig = hash_hmac('sha256', $timestamp . '.' . $body, $secret)
 *
 * Headers:
 *   X-Timestamp: <epoch seconds>
 *   X-Signature: sha256=<hex digest>
 *
 * Получатель повторяет вычисление, проверяет equality + timestamp ≤ 5 минут.
 *
 * Формирование подписи делается через `WebhookSignature::compute()`; верификация —
 * через `WebhookSignature::verify()`. Используется `hash_equals` для timing-safe сравнения.
 */
final readonly class WebhookSignature
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^sha256=[a-f0-9]{64}$/', $value)) {
            throw new InvalidArgumentException("WebhookSignature must be `sha256=<64 hex>`, got '{$value}'.");
        }
    }

    public static function compute(WebhookSecret $secret, int $timestamp, string $body): self
    {
        $digest = hash_hmac('sha256', $timestamp.'.'.$body, $secret->value);
        return new self("sha256={$digest}");
    }

    public function verify(WebhookSecret $secret, int $timestamp, string $body): bool
    {
        $expected = self::compute($secret, $timestamp, $body);
        return hash_equals($expected->value, $this->value);
    }
}
