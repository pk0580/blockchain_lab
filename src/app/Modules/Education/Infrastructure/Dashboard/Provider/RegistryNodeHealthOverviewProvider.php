<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Dashboard\Provider;

use App\Modules\Education\Application\Contract\Dashboard\NodeHealthOverviewProvider;
use App\Modules\Education\Application\DTO\Dashboard\NodeHealthEndpointRow;
use App\Modules\Education\Application\DTO\Dashboard\NodeHealthSection;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthRegistry;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointKey;

/**
 * Берёт список всех сетей из `ChainRepository` (shared kernel), а потом для
 * каждого HTTP-endpoint'а спрашивает `EndpointHealthRegistry` про последнее
 * наблюдение. Registry — cache-backed, поэтому это один-два Redis-вызова
 * на endpoint без БД.
 *
 * Если probe job ещё не запускался — последнее observation = null, status
 * остается `Unknown`, остальные поля null. UI это явно подсвечивает.
 */
final readonly class RegistryNodeHealthOverviewProvider implements NodeHealthOverviewProvider
{
    public function __construct(
        private ChainRepository $chains,
        private EndpointHealthRegistry $registry,
    ) {}

    public function load(): NodeHealthSection
    {
        $rows = [];
        foreach ($this->chains->all() as $chain) {
            foreach ($this->httpEndpointsOf($chain) as $url) {
                $key = new EndpointKey($chain->id, $url);
                $obs = $this->registry->lastObservation($key);
                // Если observation'а нет — спрашиваем статус отдельно (registry
                // в этом случае вернёт Unknown). Если есть — берём из него,
                // чтобы консистентно показать всю наблюдение целиком.
                $status = $obs !== null ? $obs->status : $this->registry->status($key);

                $rows[] = new NodeHealthEndpointRow(
                    chainId: $chain->id->value,
                    chainName: $chain->name->value,
                    chainFamily: $chain->family->value,
                    endpointUrl: $url,
                    status: $status->value,
                    headHeight: $obs?->headHeight,
                    latencyMs: $obs?->latencyMs,
                    observedAt: $obs !== null ? $obs->observedAt->format(DATE_ATOM) : null,
                    error: $obs?->error,
                );
            }
        }
        return new NodeHealthSection($rows);
    }

    /**
     * @return list<string>
     */
    private function httpEndpointsOf(Chain $chain): array
    {
        $urls = [];
        foreach ($chain->endpoints() as $endpoint) {
            if ($endpoint->kind === RpcKind::Http) {
                $urls[] = $endpoint->url;
            }
        }
        return $urls;
    }
}
