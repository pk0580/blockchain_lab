<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Exception;

use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use RuntimeException;

/**
 * Возникает при попытке использовать ранее увиденный Idempotency-Key с другим
 * payload. См. `.claude/rules/advanced_patterns.md` → Idempotency: разные
 * запросы под одним ключом запрещены, потому что иначе клиент случайно
 * перетрёт реальную транзакцию.
 */
final class IdempotencyConflictException extends RuntimeException
{
    public static function payloadMismatch(IdempotencyKey $key): self
    {
        return new self(
            "Idempotency-Key '{$key->value}' was reused with a different request payload."
        );
    }
}
