<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

final readonly class ChainName
{
    public function __construct(public string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '' || mb_strlen($trimmed) > 100) {
            throw new InvalidArgumentException('ChainName must be 1..100 chars.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
