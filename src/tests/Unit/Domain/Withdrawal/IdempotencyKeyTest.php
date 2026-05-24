<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Withdrawal;

use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use InvalidArgumentException;

it('accepts URL-safe keys of allowed length', function (): void {
    $k = new IdempotencyKey('order-2026-05-22-abc');
    expect((string) $k)->toBe('order-2026-05-22-abc');
});

it('rejects keys shorter than 8 chars', function (): void {
    expect(fn () => new IdempotencyKey('short'))->toThrow(InvalidArgumentException::class);
});

it('rejects keys longer than 120 chars', function (): void {
    expect(fn () => new IdempotencyKey(str_repeat('a', 121)))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects whitespace and unsafe punctuation', function (): void {
    expect(fn () => new IdempotencyKey('with space here'))->toThrow(InvalidArgumentException::class);
    expect(fn () => new IdempotencyKey('with#hash#here'))->toThrow(InvalidArgumentException::class);
});
