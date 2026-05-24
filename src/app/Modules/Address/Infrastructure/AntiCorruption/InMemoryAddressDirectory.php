<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\AntiCorruption;

use App\Modules\BlockIngestion\Domain\Contract\AddressDirectory;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

/**
 * Test double for AddressDirectory. Used in unit / feature tests so we
 * don't need a live Redis instance. Bound in tests via $this->swap() or
 * directly through the container.
 */
final class InMemoryAddressDirectory implements AddressDirectory
{
    /** @var array<string, array<string, true>> */
    private array $sets = [];

    public function isWatched(ChainFamily $family, string $address): bool
    {
        return isset($this->sets[$family->value][$address]);
    }

    public function register(ChainFamily $family, string $address): void
    {
        if ($address === '') {
            return;
        }
        $this->sets[$family->value][$address] = true;
    }

    public function flush(ChainFamily $family): void
    {
        unset($this->sets[$family->value]);
    }
}
