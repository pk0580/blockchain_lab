<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Infrastructure\Persistence;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthRegistry;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointKey;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointObservation;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointStatus;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Cache-backed реализация registry. Phase 7.1 хранит:
 *   - `node_health:{chain_id}:{sha256(url)}` → массив с last observation;
 *   - `node_health:index:{chain_id}` → массив известных URL для chain'а (нужно
 *     для `knownEndpointsFor()` — pick может не знать какие endpoint'ы есть
 *     на cold-start, поэтому index пишется на каждом `record()`).
 *
 * TTL ставим 1 час: probe job обновляет каждые 30 сек, протухание окно — ok.
 * Если probe job встанет — записи протухнут, picker уйдёт на fallback (Unknown
 * trumps nothing-known).
 */
final readonly class CacheEndpointHealthRegistry implements EndpointHealthRegistry
{
    private const TTL_SECONDS = 3600;

    public function __construct(private CacheRepository $cache) {}

    public function status(EndpointKey $key): EndpointStatus
    {
        $row = $this->cache->get($key->cacheKey());
        if (! is_array($row) || ! isset($row['status']) || ! is_string($row['status'])) {
            return EndpointStatus::Unknown;
        }
        return EndpointStatus::from($row['status']);
    }

    public function lastObservation(EndpointKey $key): ?EndpointObservation
    {
        $row = $this->cache->get($key->cacheKey());
        if (! is_array($row)) {
            return null;
        }
        $status = $row['status'] ?? null;
        $observedAt = $row['observed_at'] ?? null;
        if (! is_string($status) || ! is_string($observedAt)) {
            return null;
        }
        $headHeight = $row['head_height'] ?? null;
        $latencyMs = $row['latency_ms'] ?? null;
        $error = $row['error'] ?? null;

        return new EndpointObservation(
            status: EndpointStatus::from($status),
            headHeight: is_int($headHeight) ? $headHeight : null,
            latencyMs: is_int($latencyMs) ? $latencyMs : null,
            observedAt: new DateTimeImmutable($observedAt),
            error: is_string($error) ? $error : null,
        );
    }

    public function record(EndpointKey $key, EndpointObservation $observation): void
    {
        $this->cache->put($key->cacheKey(), [
            'status' => $observation->status->value,
            'head_height' => $observation->headHeight,
            'latency_ms' => $observation->latencyMs,
            'observed_at' => $observation->observedAt->format(DATE_ATOM),
            'error' => $observation->error,
            'url' => $key->url,
        ], self::TTL_SECONDS);

        $indexKey = $this->indexKey($key->chainId);
        /** @var list<string> $index */
        $index = (array) $this->cache->get($indexKey, []);
        if (! in_array($key->url, $index, strict: true)) {
            $index[] = $key->url;
            $this->cache->put($indexKey, $index, self::TTL_SECONDS);
        }
    }

    public function knownEndpointsFor(ChainId $chainId): array
    {
        $index = (array) $this->cache->get($this->indexKey($chainId), []);
        $keys = [];
        foreach ($index as $url) {
            if (is_string($url) && $url !== '') {
                $keys[] = new EndpointKey($chainId, $url);
            }
        }
        return $keys;
    }

    private function indexKey(ChainId $chainId): string
    {
        return "node_health:index:{$chainId->value}";
    }
}
