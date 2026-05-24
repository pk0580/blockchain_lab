<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\NodeHealth;

use App\Modules\NodeHealth\Domain\ValueObject\EndpointStatus;

it('marks Healthy/Degraded/Unknown as usable', function (): void {
    expect(EndpointStatus::Healthy->isUsable())->toBeTrue();
    expect(EndpointStatus::Degraded->isUsable())->toBeTrue();
    expect(EndpointStatus::Unknown->isUsable())->toBeTrue();
});

it('marks Unhealthy as not usable', function (): void {
    expect(EndpointStatus::Unhealthy->isUsable())->toBeFalse();
});
