<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

final readonly class BlockHash
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^(0x)?[0-9a-fA-F]{40,128}$/', $value)) {
            throw new InvalidArgumentException("BlockHash must be hex (40-128 hex chars): '{$value}'.");
        }
    }

    public function normalized(): string
    {
        return strtolower(str_starts_with($this->value, '0x') ? $this->value : '0x'.$this->value);
    }

    public function equals(self $other): bool
    {
        return $this->normalized() === $other->normalized();
    }
}
