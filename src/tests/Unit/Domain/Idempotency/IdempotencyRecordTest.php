<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Idempotency;

use App\Modules\Idempotency\Domain\Entity\IdempotencyRecord;
use App\Modules\Idempotency\Domain\ValueObject\HttpMethod;
use App\Modules\Idempotency\Domain\ValueObject\HttpPath;
use App\Modules\Idempotency\Domain\ValueObject\IdempotencyKey;
use App\Modules\Idempotency\Domain\ValueObject\RequestHash;
use App\Modules\Idempotency\Domain\ValueObject\ResponseSnapshot;
use DateTimeImmutable;
use InvalidArgumentException;

function buildRecord(
    string $hash = '',
    string $createdAt = '2026-01-01T00:00:00Z',
    string $expiresAt = '2026-01-02T00:00:00Z',
): IdempotencyRecord {
    return new IdempotencyRecord(
        key: new IdempotencyKey('test-key-001'),
        requestHash: new RequestHash($hash !== '' ? $hash : hash('sha256', 'x')),
        method: new HttpMethod('POST'),
        path: new HttpPath('/api/v1/x'),
        response: new ResponseSnapshot(201, '{"data":"ok"}'),
        createdAt: new DateTimeImmutable($createdAt),
        expiresAt: new DateTimeImmutable($expiresAt),
    );
}

it('requires expiresAt strictly after createdAt', function (): void {
    expect(fn () => buildRecord(
        createdAt: '2026-01-01T00:00:00Z',
        expiresAt: '2026-01-01T00:00:00Z',
    ))->toThrow(InvalidArgumentException::class);
});

it('matches with the same request hash and rejects others', function (): void {
    $r = buildRecord(hash('sha256', 'body-a'));
    expect($r->matches(new RequestHash(hash('sha256', 'body-a'))))->toBeTrue();
    expect($r->matches(new RequestHash(hash('sha256', 'body-b'))))->toBeFalse();
});

it('detects expiration', function (): void {
    $r = buildRecord(expiresAt: '2026-01-02T00:00:00Z');
    expect($r->isExpired(new DateTimeImmutable('2026-01-01T23:59:59Z')))->toBeFalse();
    expect($r->isExpired(new DateTimeImmutable('2026-01-02T00:00:00Z')))->toBeTrue();
    expect($r->isExpired(new DateTimeImmutable('2026-01-02T00:00:01Z')))->toBeTrue();
});
