<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Domain\Contract;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointObservation;

/**
 * Per-family probe: выполняет один лёгкий RPC-вызов на конкретный endpoint
 * и возвращает observation. НИКОГДА не бросает исключение — сбой узла
 * — это успешный probe со status=Unhealthy.
 */
interface EndpointHealthProbe
{
    public function probe(Chain $chain, RpcEndpoint $endpoint): EndpointObservation;
}
