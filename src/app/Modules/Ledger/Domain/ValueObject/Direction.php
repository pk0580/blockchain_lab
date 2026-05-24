<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ValueObject;

enum Direction: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function opposite(): self
    {
        return $this === self::Credit ? self::Debit : self::Credit;
    }
}
