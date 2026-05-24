<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Exception;

use DomainException;

final class UnknownChainFamilyException extends DomainException
{
    public function __construct(string $value)
    {
        parent::__construct("Unknown chain family: '{$value}' (expected: bitcoin|evm|tron).");
    }
}
