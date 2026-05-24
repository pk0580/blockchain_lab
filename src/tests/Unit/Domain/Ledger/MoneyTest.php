<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ledger;

use App\Modules\Ledger\Domain\ValueObject\Money;

it('accepts large integer string amounts up to 40 digits', function (): void {
    $money = new Money(str_repeat('9', 40), 'WEI');
    expect($money->amount)->toBe(str_repeat('9', 40));
});

it('accepts zero', function (): void {
    expect((new Money('0', 'BTC'))->amount)->toBe('0');
});

it('rejects negative amount', function (): void {
    new Money('-1', 'BTC');
})->throws(\InvalidArgumentException::class);

it('rejects floating point amount', function (): void {
    new Money('1.5', 'BTC');
})->throws(\InvalidArgumentException::class);

it('rejects amount with leading zeroes', function (): void {
    new Money('01', 'BTC');
})->throws(\InvalidArgumentException::class);

it('rejects amount longer than 40 digits', function (): void {
    new Money(str_repeat('1', 41), 'BTC');
})->throws(\InvalidArgumentException::class);

it('rejects lower-case currency', function (): void {
    new Money('100', 'btc');
})->throws(\InvalidArgumentException::class);

it('compares by value', function (): void {
    expect((new Money('100', 'BTC'))->equals(new Money('100', 'BTC')))->toBeTrue();
    expect((new Money('100', 'BTC'))->equals(new Money('100', 'ETH')))->toBeFalse();
    expect((new Money('100', 'BTC'))->equals(new Money('101', 'BTC')))->toBeFalse();
});
