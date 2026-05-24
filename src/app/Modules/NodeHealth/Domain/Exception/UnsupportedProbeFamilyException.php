<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use RuntimeException;

final class UnsupportedProbeFamilyException extends RuntimeException
{
    public static function forFamily(ChainFamily $family): self
    {
        return new self("No EndpointHealthProbe registered for family '{$family->value}'.");
    }
}
