<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Infrastructure\Probe;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Infrastructure\Rpc\EvmJsonRpc;
use App\Modules\Network\Infrastructure\Rpc\RpcError;
use App\Modules\Network\Infrastructure\Rpc\TransportError;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthProbe;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointObservation;
use DateTimeImmutable;
use Throwable;

/**
 * EVM health probe (GUIDE.md, Урок 12.4): `eth_blockNumber`.
 *
 * Логика:
 *   - меряем RTT;
 *   - любая ошибка (RPC/transport/прочее) → {@see EndpointObservation::unhealthy()};
 *   - latency ≥ 2000 ms → {@see EndpointObservation::degraded()};
 *   - иначе → {@see EndpointObservation::healthy()}.
 *
 * @see \GUIDE.md  Урок 12 (#урок-12--надёжность-и-наблюдаемость)
 */
final readonly class EvmEndpointHealthProbe implements EndpointHealthProbe
{
    private const DEGRADED_LATENCY_MS = 2000;

    public function __construct(private EvmJsonRpc $rpc) {}

    public function probe(Chain $chain, RpcEndpoint $endpoint): EndpointObservation
    {
        $start = (int) (microtime(true) * 1000);

        try {
            $height = $this->rpc->blockNumber($endpoint->url);
        } catch (RpcError $e) {
            return EndpointObservation::unhealthy(
                error: 'rpc:'.$e->getMessage(),
                observedAt: new DateTimeImmutable(),
            );
        } catch (TransportError $e) {
            return EndpointObservation::unhealthy(
                error: 'transport:'.$e->getMessage(),
                observedAt: new DateTimeImmutable(),
            );
        } catch (Throwable $e) {
            return EndpointObservation::unhealthy(
                error: $e->getMessage(),
                observedAt: new DateTimeImmutable(),
            );
        }

        $latencyMs = max(0, ((int) (microtime(true) * 1000)) - $start);
        $now = new DateTimeImmutable();

        if ($latencyMs >= self::DEGRADED_LATENCY_MS) {
            return EndpointObservation::degraded(
                headHeight: $height,
                latencyMs: $latencyMs,
                observedAt: $now,
                reason: "slow response ({$latencyMs} ms)",
            );
        }

        return EndpointObservation::healthy(
            headHeight: $height,
            latencyMs: $latencyMs,
            observedAt: $now,
        );
    }
}
