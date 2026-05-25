<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Symmetric secret для HMAC-SHA256 подписи. Сейчас хранится в БД plaintext;
 * envelope encryption через Laravel Encrypter — потенциальное усиление.
 *
 * Длина 32..128 chars; короче нет смысла (256 bits == 32 bytes hex = 64 chars
 * recommended). НЕ хранится в логах: метод `__toString()` намеренно отсутствует.
 */
final readonly class WebhookSecret
{
    public function __construct(public string $value)
    {
        $len = strlen($value);
        if ($len < 32 || $len > 128) {
            throw new InvalidArgumentException(
                "WebhookSecret length must be 32..128 chars, got {$len}."
            );
        }
    }

    /**
     * Маскированное представление для логов / dashboards (последние 4 char'а).
     */
    public function masked(): string
    {
        return '****'.substr($this->value, -4);
    }
}
