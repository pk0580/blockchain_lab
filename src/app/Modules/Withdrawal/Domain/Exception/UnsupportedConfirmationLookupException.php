<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use RuntimeException;

final class UnsupportedConfirmationLookupException extends RuntimeException
{
    public static function forFamily(ChainFamily $family): self
    {
        return new self(
            "No confirmation lookup registered for chain family '{$family->value}'."
        );
    }
}
