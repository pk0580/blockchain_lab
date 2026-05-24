<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\Exception;

use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTxStatus;
use DomainException;

final class InvalidStatusTransitionException extends DomainException
{
    public static function from(IncomingTxStatus $from, IncomingTxStatus $to): self
    {
        return new self("Cannot transition incoming tx from '{$from->value}' to '{$to->value}'.");
    }
}
