<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Замороженный response — status + body. Хедеры не сохраняем: middleware
 * добавит `Content-Type: application/json` + `X-Idempotent-Replay: true`
 * на стороне UI. Минимизация surface'а упрощает миграцию (нет JSONB с
 * хедерами, нет проблем с PII в `Authorization`/`Set-Cookie`).
 */
final readonly class ResponseSnapshot
{
    /**
     * Сохраняемый диапазон. 1xx исключаем (нет тела); 5xx — это transient,
     * клиент должен повторить, а не получить кешированную ошибку.
     */
    public const int MIN_STATUS = 200;
    public const int MAX_STATUS = 499;

    public function __construct(
        public int $status,
        public string $body,
    ) {
        if ($status < self::MIN_STATUS || $status > self::MAX_STATUS) {
            throw new InvalidArgumentException(
                "ResponseSnapshot status must be in range "
                .self::MIN_STATUS.'..'.self::MAX_STATUS.", got {$status}."
            );
        }
    }

    public static function isStorableStatus(int $status): bool
    {
        return $status >= self::MIN_STATUS && $status <= self::MAX_STATUS;
    }
}
