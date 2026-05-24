<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

final readonly class BlockHeight
{
    public function __construct(public int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException("BlockHeight cannot be negative: {$value}.");
        }
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function distanceTo(self $head): int
    {
        return max(0, $head->value - $this->value);
    }
}
