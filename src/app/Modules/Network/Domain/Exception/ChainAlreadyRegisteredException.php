<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Exception;

use App\Modules\Network\Domain\ValueObject\ChainId;
use DomainException;

final class ChainAlreadyRegisteredException extends DomainException
{
    public static function byId(ChainId $id): self
    {
        return new self("Chain '{$id->value}' is already registered.");
    }
}
