<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Application\UseCase\ProbeChainEndpoints;

final readonly class ProbeChainEndpointsData
{
    public function __construct(public string $chainId) {}
}
