<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\NodeHealth;

use App\Modules\NodeHealth\Domain\ValueObject\EndpointObservation;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointStatus;
use DateTimeImmutable;
use InvalidArgumentException;

it('builds a healthy observation via factory', function (): void {
    $now = new DateTimeImmutable();
    $o = EndpointObservation::healthy(headHeight: 100, latencyMs: 50, observedAt: $now);

    expect($o->status)->toBe(EndpointStatus::Healthy);
    expect($o->headHeight)->toBe(100);
    expect($o->latencyMs)->toBe(50);
    expect($o->error)->toBeNull();
});

it('builds a degraded observation with reason', function (): void {
    $o = EndpointObservation::degraded(
        headHeight: 100,
        latencyMs: 2500,
        observedAt: new DateTimeImmutable(),
        reason: 'slow',
    );
    expect($o->status)->toBe(EndpointStatus::Degraded);
    expect($o->error)->toBe('slow');
});

it('builds an unhealthy observation with mandatory error', function (): void {
    $o = EndpointObservation::unhealthy(error: 'connection refused', observedAt: new DateTimeImmutable());
    expect($o->status)->toBe(EndpointStatus::Unhealthy);
    expect($o->headHeight)->toBeNull();
    expect($o->latencyMs)->toBeNull();
});

it('rejects Unknown status', function (): void {
    expect(fn () => new EndpointObservation(
        EndpointStatus::Unknown, 0, 0, new DateTimeImmutable(),
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects Unhealthy without error reason', function (): void {
    expect(fn () => new EndpointObservation(
        EndpointStatus::Unhealthy, null, null, new DateTimeImmutable(), null,
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects Healthy/Degraded without headHeight', function (): void {
    expect(fn () => new EndpointObservation(
        EndpointStatus::Healthy, null, 10, new DateTimeImmutable(),
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects negative headHeight / latency', function (): void {
    expect(fn () => new EndpointObservation(
        EndpointStatus::Healthy, -1, 10, new DateTimeImmutable(),
    ))->toThrow(InvalidArgumentException::class);
});
