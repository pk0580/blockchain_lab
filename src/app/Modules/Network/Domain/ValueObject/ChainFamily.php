<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use App\Modules\Network\Domain\Exception\UnknownChainFamilyException;

enum ChainFamily: string
{
    case Bitcoin = 'bitcoin';
    case Evm = 'evm';
    case Tron = 'tron';

    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower($value))
            ?? throw new UnknownChainFamilyException($value);
    }
}
