<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Application\UseCase\RecordIdempotentResponse;

use App\Modules\Idempotency\Domain\Contract\Clock;
use App\Modules\Idempotency\Domain\Contract\IdempotencyStore;
use App\Modules\Idempotency\Domain\Entity\IdempotencyRecord;
use App\Modules\Idempotency\Domain\ValueObject\HttpMethod;
use App\Modules\Idempotency\Domain\ValueObject\HttpPath;
use App\Modules\Idempotency\Domain\ValueObject\IdempotencyKey;
use App\Modules\Idempotency\Domain\ValueObject\RequestHash;
use App\Modules\Idempotency\Domain\ValueObject\ResponseSnapshot;

/**
 * Фиксирует response в store. TTL приходит в секундах извне (config-driven),
 * чтобы Application не тянул `config()`. Если status не storable (например 5xx)
 * — middleware просто не зовёт эту Action, чтобы не закешировать transient
 * сбой.
 */
final readonly class RecordIdempotentResponseAction
{
    public function __construct(
        private IdempotencyStore $store,
        private Clock $clock,
    ) {}

    public function handle(
        IdempotencyKey $key,
        RequestHash $hash,
        HttpMethod $method,
        HttpPath $path,
        ResponseSnapshot $response,
        int $ttlSeconds,
    ): void {
        $now = $this->clock->now();
        $expiresAt = $now->modify("+{$ttlSeconds} seconds");

        $this->store->save(new IdempotencyRecord(
            key: $key,
            requestHash: $hash,
            method: $method,
            path: $path,
            response: $response,
            createdAt: $now,
            expiresAt: $expiresAt,
        ));
    }
}
