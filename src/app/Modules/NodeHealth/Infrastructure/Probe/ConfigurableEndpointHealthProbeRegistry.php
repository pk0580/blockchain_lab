<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Infrastructure\Probe;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthProbe;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthProbeRegistry;
use App\Modules\NodeHealth\Domain\Exception\UnsupportedProbeFamilyException;

final readonly class ConfigurableEndpointHealthProbeRegistry implements EndpointHealthProbeRegistry
{
    /**
     * @param array<string, EndpointHealthProbe> $byFamily
     */
    public function __construct(private array $byFamily) {}

    public function for(ChainFamily $family): EndpointHealthProbe
    {
        return $this->byFamily[$family->value]
            ?? throw UnsupportedProbeFamilyException::forFamily($family);
    }
}
