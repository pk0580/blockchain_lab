<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Nonce;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Withdrawal\Domain\Contract\NonceProbe;
use App\Modules\Withdrawal\Domain\Contract\NonceProbeRegistry;

final readonly class ConfigurableNonceProbeRegistry implements NonceProbeRegistry
{
    /**
     * @param array<string, NonceProbe> $probesByFamily ключ — ChainFamily::value
     */
    public function __construct(private array $probesByFamily) {}

    public function for(ChainFamily $family): ?NonceProbe
    {
        return $this->probesByFamily[$family->value] ?? null;
    }
}
