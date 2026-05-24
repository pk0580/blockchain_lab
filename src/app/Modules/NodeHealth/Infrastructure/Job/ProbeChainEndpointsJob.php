<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Infrastructure\Job;

use App\Modules\NodeHealth\Application\UseCase\ProbeChainEndpoints\ProbeChainEndpointsAction;
use App\Modules\NodeHealth\Application\UseCase\ProbeChainEndpoints\ProbeChainEndpointsData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Per-chain probe tick. Очередь `health.{chain_id}` обеспечивает bulkhead:
 * timeout на одной chain не задерживает probing других.
 */
final class ProbeChainEndpointsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly string $chainId)
    {
        $this->onQueue("health.{$chainId}");
    }

    public function handle(ProbeChainEndpointsAction $action): void
    {
        $action->handle(new ProbeChainEndpointsData($this->chainId));
    }
}
