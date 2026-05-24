<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\ValueObject;

use InvalidArgumentException;

/**
 * URL подписчика. https only в проде; http разрешён только для тестов (через
 * `WEBHOOK_ALLOW_INSECURE=true`). Без такого ключа: попытка передать http://
 * — это конфигурационная ошибка, не runtime-сюрприз.
 */
final readonly class WebhookUrl
{
    public function __construct(public string $value, bool $allowInsecure = false)
    {
        $parsed = parse_url($value);
        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            throw new InvalidArgumentException("WebhookUrl is malformed: '{$value}'.");
        }
        $allowed = $allowInsecure ? ['http', 'https'] : ['https'];
        if (! in_array($parsed['scheme'], $allowed, strict: true)) {
            throw new InvalidArgumentException(
                "WebhookUrl scheme must be ".implode('/', $allowed).", got '{$parsed['scheme']}'."
            );
        }
        if (strlen($value) > 500) {
            throw new InvalidArgumentException('WebhookUrl too long (>500 chars).');
        }
    }
}
