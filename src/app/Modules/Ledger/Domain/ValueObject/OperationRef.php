<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Указатель на источник операции: для Deposit это incoming_transactions.id,
 * для ReorgReversal — тоже id оригинальной credit-записи. Использование
 * того же VO для разных типов операций решено намеренно: запросы по
 * operation_id одинаковы и для credit, и для reversal.
 */
final readonly class OperationRef
{
    public function __construct(public string $value)
    {
        if ($value === '' || strlen($value) > 64) {
            throw new InvalidArgumentException("OperationRef must be 1-64 chars, got '{$value}'.");
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
