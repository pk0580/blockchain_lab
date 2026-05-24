<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Address;

use App\Modules\Address\Domain\ValueObject\HdSeedReference;

it('accepts alnum and dash/underscore, 4-64 chars', function (): void {
    expect((string) new HdSeedReference('user-001'))->toBe('user-001');
    expect((string) new HdSeedReference(str_repeat('a', 64)))->toHaveLength(64);
});

it('rejects too short', function (): void {
    new HdSeedReference('abc');
})->throws(\InvalidArgumentException::class);

it('rejects forbidden chars', function (): void {
    new HdSeedReference('with space');
})->throws(\InvalidArgumentException::class);

it('rejects too long', function (): void {
    new HdSeedReference(str_repeat('a', 65));
})->throws(\InvalidArgumentException::class);
