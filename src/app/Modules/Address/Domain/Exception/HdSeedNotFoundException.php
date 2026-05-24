<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\Exception;

use App\Modules\Address\Domain\ValueObject\HdSeedId;
use DomainException;

final class HdSeedNotFoundException extends DomainException
{
    public static function byId(HdSeedId $id): self
    {
        return new self("HdSeed '{$id->value}' not found.");
    }
}
