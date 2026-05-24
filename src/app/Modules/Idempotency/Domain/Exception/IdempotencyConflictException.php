<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Domain\Exception;

use App\Modules\Idempotency\Domain\ValueObject\IdempotencyKey;
use DomainException;

/**
 * Тот же `Idempotency-Key` использован с другим телом запроса. Это всегда
 * программная ошибка клиента: либо неправильно ретраит, либо переиспользует
 * UUID. Middleware маппит exception в HTTP 409 `idempotency_conflict`.
 */
final class IdempotencyConflictException extends DomainException
{
    public static function for(IdempotencyKey $key): self
    {
        return new self(
            "Idempotency-Key '{$key->value}' reused with a different request body."
        );
    }
}
