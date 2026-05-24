<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Withdrawal;

use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;

it('stores trimmed value', function (): void {
    $h = new HotAddress('0xdeadbeef');
    expect($h->value)->toBe('0xdeadbeef');
    expect((string) $h)->toBe('0xdeadbeef');
});

it('rejects empty / whitespace-only', function (): void {
    new HotAddress('   ');
})->throws(\InvalidArgumentException::class);

it('rejects values longer than 96 chars', function (): void {
    new HotAddress(str_repeat('a', 97));
})->throws(\InvalidArgumentException::class);

it('compares by value', function (): void {
    expect((new HotAddress('abc'))->equals(new HotAddress('abc')))->toBeTrue();
    expect((new HotAddress('abc'))->equals(new HotAddress('abd')))->toBeFalse();
});
