<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Локальный VO Withdrawal::Domain. Не импортируем будущий `Wallet::Domain` —
 * этот bounded context ещё не построен. Контракт: kebab-case / uuid / opaque-id.
 */
final readonly class WalletId
{
    public function __construct(public string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '' || strlen($trimmed) > 64) {
            throw new InvalidArgumentException(
                "WalletId must be a non-empty string up to 64 chars, got '{$value}'."
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
