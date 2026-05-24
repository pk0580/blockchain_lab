<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fee;

use App\Modules\Fee\Domain\ValueObject\FeePriority;

it('parses canonical lowercase tokens', function (): void {
    expect(FeePriority::fromString('low'))->toBe(FeePriority::Low);
    expect(FeePriority::fromString('standard'))->toBe(FeePriority::Standard);
    expect(FeePriority::fromString('high'))->toBe(FeePriority::High);
});

it('parses case-insensitively', function (): void {
    expect(FeePriority::fromString('HIGH'))->toBe(FeePriority::High);
    expect(FeePriority::fromString('Standard'))->toBe(FeePriority::Standard);
});

it('rejects unknown values', function (): void {
    FeePriority::fromString('urgent');
})->throws(\InvalidArgumentException::class);
