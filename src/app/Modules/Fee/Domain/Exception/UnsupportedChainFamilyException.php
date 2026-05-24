<?php

declare(strict_types=1);

namespace App\Modules\Fee\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use RuntimeException;

final class UnsupportedChainFamilyException extends RuntimeException
{
    public static function forFamily(ChainFamily $family): self
    {
        return new self(
            "No FeeEstimator registered for chain family '{$family->value}'."
        );
    }
}
