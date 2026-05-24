<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainId;
use DomainException;

final class ScanCursorMissingException extends DomainException
{
    public static function forChain(ChainId $chainId): self
    {
        return new self("ScanCursor for chain '{$chainId->value}' has not been initialised.");
    }
}
