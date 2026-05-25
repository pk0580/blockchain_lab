<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Infrastructure\Probe;

use App\Modules\BlockIngestion\Domain\Exception\BlockSourceException;
use App\Modules\BlockIngestion\Infrastructure\BlockSource\BitcoinRpcClient;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthProbe;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointObservation;
use Closure;
use DateTimeImmutable;
use Throwable;

/**
 * Bitcoin health probe (GUIDE.md, Урок 12.4): `getblockcount` (дешевейший RPC).
 *
 * Логика та же, что у {@see EvmEndpointHealthProbe}:
 *   - меряем RTT;
 *   - любая ошибка → Unhealthy;
 *   - ≥ 2000 ms → Degraded;
 *   - иначе → Healthy.
 *
 * Сравнение head_height с другими пирами (lag-detection) пока не реализовано.
 *
 * @see \GUIDE.md  Урок 12 (#урок-12--надёжность-и-наблюдаемость)
 */
final readonly class BitcoinEndpointHealthProbe implements EndpointHealthProbe
{
    private const DEGRADED_LATENCY_MS = 2000;

    public function __construct(
        /** @var Closure(Chain, RpcEndpoint): BitcoinRpcClient */
        private Closure $rpcFactory,
    ) {}

    public function probe(Chain $chain, RpcEndpoint $endpoint): EndpointObservation
    {
        $rpc = ($this->rpcFactory)($chain, $endpoint);
        $start = (int) (microtime(true) * 1000);

        try {
            $height = $rpc->getBlockCount();
        } catch (BlockSourceException $e) {
            return EndpointObservation::unhealthy(
                error: $e->getMessage(),
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
