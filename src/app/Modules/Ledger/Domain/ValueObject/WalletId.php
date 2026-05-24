<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Локальная копия WalletId для Ledger-домена. Address::Domain владеет
 * собственным WalletId — он не импортируется сюда, чтобы Ledger не зависел от
 * соседнего модуля. Формат тот же (UUIDv4), bridge между двумя
 * представлениями выполняет WalletOwnership-адаптер в Infrastructure.
 */
final readonly class WalletId
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            throw new InvalidArgumentException("WalletId must be a UUID, got '{$value}'.");
        }
    }

    public function equals(self $other): bool
    {
        return strcasecmp($this->value, $other->value) === 0;
    }
}
