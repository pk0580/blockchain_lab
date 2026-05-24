<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Application\UseCase\ProbeEndpoint;

use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\NodeHealth\Application\Contract\NodeHealthEventDispatcher;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthProbeRegistry;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthRegistry;
use App\Modules\NodeHealth\Domain\Event\EndpointHealthChanged;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointKey;
use DateTimeImmutable;
use RuntimeException;

/**
 * Опрашивает один endpoint, обновляет registry, эмитит EndpointHealthChanged
 * если статус изменился. Никогда не падает на сетевом сбое — probe сам
 * превращает любую ошибку в observation::unhealthy().
 *
 * Идемпотентен: повторный probe с тем же статусом просто обновляет timestamp,
 * события не эмитит.
 */
final readonly class ProbeEndpointAction
{
    public function __construct(
        private ChainRepository $chains,
        private EndpointHealthProbeRegistry $probes,
        private EndpointHealthRegistry $registry,
        private NodeHealthEventDispatcher $events,
    ) {}

    public function handle(ProbeEndpointData $data): ProbeEndpointResult
    {
        $chainId = new ChainId($data->chainId);
        $chain = $this->chains->findById($chainId)
            ?? throw ChainNotFoundException::byId($chainId);

        $endpoint = null;
        foreach ($chain->endpoints() as $candidate) {
            if ($candidate->url === $data->url) {
                $endpoint = $candidate;
                break;
            }
        }
        if ($endpoint === null) {
            throw new RuntimeException(
                "Chain '{$chainId->value}' has no endpoint with URL '{$data->url}'."
            );
        }

        $key = new EndpointKey($chainId, $endpoint->url);
        $previous = $this->registry->status($key);

        $probe = $this->probes->for($chain->family);
        $observation = $probe->probe($chain, $endpoint);

        $this->registry->record($key, $observation);

        if ($previous !== $observation->status) {
            $this->events->dispatch(new EndpointHealthChanged(
                endpoint: $key,
                from: $previous,
                to: $observation->status,
                occurredAt: new DateTimeImmutable(),
            ));
        }

        return new ProbeEndpointResult(
            previous: $previous,
            current: $observation->status,
            headHeight: $observation->headHeight,
            latencyMs: $observation->latencyMs,
        );
    }
}
