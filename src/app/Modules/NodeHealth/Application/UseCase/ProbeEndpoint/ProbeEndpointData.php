<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Application\UseCase\ProbeEndpoint;

final readonly class ProbeEndpointData
{
    public function __construct(
        public string $chainId,
        public string $url,
    ) {}
}
