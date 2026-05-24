<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Withdrawal;

use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;

it('accepts zero', function (): void {
    expect((new NonceValue(0))->value)->toBe(0);
});

it('exposes monotonic next()', function (): void {
    $n = new NonceValue(41);
    $next = $n->next();
    expect($next->value)->toBe(42);
    expect($n->value)->toBe(41);  // original immutable
});

it('rejects negative values', function (): void {
    new NonceValue(-1);
})->throws(\InvalidArgumentException::class);

it('compares by value', function (): void {
    expect((new NonceValue(7))->equals(new NonceValue(7)))->toBeTrue();
    expect((new NonceValue(7))->equals(new NonceValue(8)))->toBeFalse();
});
