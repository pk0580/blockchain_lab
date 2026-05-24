<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Application\UseCase\ProbeChainEndpoints;

use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\NodeHealth\Application\UseCase\ProbeEndpoint\ProbeEndpointAction;
use App\Modules\NodeHealth\Application\UseCase\ProbeEndpoint\ProbeEndpointData;

/**
 * Прогоняет ProbeEndpointAction по всем HTTP endpoint'ам одной сети.
 * WS endpoint'ы Phase 7.1 не probe'им (мы их пока не используем для RPC fan-out).
 *
 * Каждый probe — независимая операция: если один endpoint timeout'нул, другие
 * всё равно обновятся.
 */
final readonly class ProbeChainEndpointsAction
{
    public function __construct(
        private ChainRepository $chains,
        private ProbeEndpointAction $probe,
    ) {}

    public function handle(ProbeChainEndpointsData $data): ProbeChainEndpointsResult
    {
        $chainId = new ChainId($data->chainId);
        $chain = $this->chains->findById($chainId)
            ?? throw ChainNotFoundException::byId($chainId);

        $probed = [];
        $changes = 0;
        foreach ($chain->endpoints() as $endpoint) {
            if ($endpoint->kind !== RpcKind::Http) {
                continue;
            }
            $result = $this->probe->handle(new ProbeEndpointData(
                chainId: $chainId->value,
                url: $endpoint->url,
            ));
            $probed[] = $endpoint->url;
            if ($result->statusChanged()) {
                $changes++;
            }
        }

        return new ProbeChainEndpointsResult(
            chainId: $chainId->value,
            probed: $probed,
            statusChanges: $changes,
        );
    }
}
