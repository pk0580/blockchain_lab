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
 * Health-check всех HTTP-эндпоинтов одной сети (GUIDE.md, Урок 12.4).
 *
 * Прогоняет {@see ProbeEndpointAction} по всем HTTP endpoint'ам сети.
 * Для каждого вызывается probe семейства ({@see EvmEndpointHealthProbe} /
 * {@see BitcoinEndpointHealthProbe}) — он делает простой RPC, меряет latency
 * и возвращает {@see EndpointObservation}: healthy / degraded (>2000 ms) / unhealthy.
 *
 * Состояние пишется в {@see CacheEndpointHealthRegistry}; если статус изменился —
 * событие EndpointHealthChanged. При выборе живой ноды
 * {@see HealthBasedRpcEndpointPicker} читает оттуда → автоматический failover
 * (паттерн circuit-breaker).
 *
 * Каждый probe — независимая операция: один upstream-таймаут не валит другие.
 *
 * @see \GUIDE.md  Урок 12 (#урок-12--надёжность-и-наблюдаемость)
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
