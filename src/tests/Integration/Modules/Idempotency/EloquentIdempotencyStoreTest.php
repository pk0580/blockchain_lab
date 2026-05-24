<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Idempotency;

use App\Modules\Idempotency\Domain\Contract\IdempotencyStore;
use App\Modules\Idempotency\Domain\Entity\IdempotencyRecord;
use App\Modules\Idempotency\Domain\ValueObject\HttpMethod;
use App\Modules\Idempotency\Domain\ValueObject\HttpPath;
use App\Modules\Idempotency\Domain\ValueObject\IdempotencyKey;
use App\Modules\Idempotency\Domain\ValueObject\RequestHash;
use App\Modules\Idempotency\Domain\ValueObject\ResponseSnapshot;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRecord(
    string $keySuffix,
    string $body = '{"a":1}',
    string $createdAt = '2026-01-01T00:00:00Z',
    string $expiresAt = '2026-01-02T00:00:00Z',
    int $status = 202,
): IdempotencyRecord {
    return new IdempotencyRecord(
        key: new IdempotencyKey('idem-'.$keySuffix),
        requestHash: RequestHash::ofRequest('POST', '/api/v1/x', $body),
        method: new HttpMethod('POST'),
        path: new HttpPath('/api/v1/x'),
        response: new ResponseSnapshot($status, '{"data":"'.$keySuffix.'"}'),
        createdAt: new DateTimeImmutable($createdAt),
        expiresAt: new DateTimeImmutable($expiresAt),
    );
}

it('round-trips a record', function (): void {
    /** @var IdempotencyStore $store */
    $store = app(IdempotencyStore::class);
    $store->save(makeRecord('001'));

    $loaded = $store->find(new IdempotencyKey('idem-001'));
    expect($loaded)->not->toBeNull();
    expect($loaded?->method->value)->toBe('POST');
    expect($loaded?->path->value)->toBe('/api/v1/x');
    expect($loaded?->response->status)->toBe(202);
    expect($loaded?->response->body)->toBe('{"data":"001"}');
});

it('returns null when key is absent', function (): void {
    /** @var IdempotencyStore $store */
    $store = app(IdempotencyStore::class);
    expect($store->find(new IdempotencyKey('missing-key-1')))->toBeNull();
});

it('upserts an existing key (last write wins)', function (): void {
    /** @var IdempotencyStore $store */
    $store = app(IdempotencyStore::class);
    $store->save(makeRecord('upd', body: '{"v":1}', status: 200));
    $store->save(makeRecord('upd', body: '{"v":2}', status: 201));

    $loaded = $store->find(new IdempotencyKey('idem-upd'));
    expect($loaded?->response->status)->toBe(201);
    expect($loaded?->matches(RequestHash::ofRequest('POST', '/api/v1/x', '{"v":2}')))
        ->toBeTrue();
});

it('deleteExpired removes rows whose expires_at <= now', function (): void {
    /** @var IdempotencyStore $store */
    $store = app(IdempotencyStore::class);
    $store->save(makeRecord('exp-a', expiresAt: '2026-01-01T00:00:01Z'));
    $store->save(makeRecord('exp-b', expiresAt: '2026-01-01T00:00:02Z'));
    $store->save(makeRecord('exp-c', expiresAt: '2026-01-02T00:00:00Z'));

    $deleted = $store->deleteExpired(new DateTimeImmutable('2026-01-01T00:00:02Z'));
    expect($deleted)->toBe(2);
    expect($store->find(new IdempotencyKey('idem-exp-a')))->toBeNull();
    expect($store->find(new IdempotencyKey('idem-exp-b')))->toBeNull();
    expect($store->find(new IdempotencyKey('idem-exp-c')))->not->toBeNull();
});
