<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\ValueObject;

use InvalidArgumentException;

/**
 * HMAC-SHA256 подпись webhook'ов (Stripe-style) — GUIDE.md, Урок 12.3.
 *
 * Алгоритм:
 *   sig = sha256=<hex(hmac_sha256(secret, timestamp + "." + body))>
 *
 * Headers, которые мы отправляем клиенту:
 *   X-Timestamp: <epoch seconds>
 *   X-Signature: sha256=<hex>
 *
 * Клиент повторяет вычисление с тем же `secret` (выданным нами при подписке),
 * сравнивает через {@see hash_equals()} (timing-safe), и проверяет, что
 * `now − timestamp ≤ 5 минут` — защита от replay (перехват и переотправка
 * через час).
 *
 * ⚠️ Использовать только `hash_equals` для сравнения подписей, никогда не `===`.
 * Иначе timing-атака может постепенно угадать байты подписи.
 *
 * @see \GUIDE.md  Урок 12 (#урок-12--надёжность-и-наблюдаемость)
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
