<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ValueObject;

enum EntryStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Reversed = 'reversed';
}
