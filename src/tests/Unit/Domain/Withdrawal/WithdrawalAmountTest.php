<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Withdrawal;

use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;
use InvalidArgumentException;

it('accepts a positive integer string', function (): void {
    expect((string) new WithdrawalAmount('1'))->toBe('1');
    expect((string) new WithdrawalAmount('1000000000000000000'))->toBe('1000000000000000000');
});

it('rejects zero', function (): void {
    expect(fn () => new WithdrawalAmount('0'))->toThrow(InvalidArgumentException::class);
});

it('rejects negatives and decimals', function (): void {
    expect(fn () => new WithdrawalAmount('-1'))->toThrow(InvalidArgumentException::class);
    expect(fn () => new WithdrawalAmount('1.5'))->toThrow(InvalidArgumentException::class);
});

it('rejects more than 40 digits', function (): void {
    expect(fn () => new WithdrawalAmount(str_repeat('9', 41)))
        ->toThrow(InvalidArgumentException::class);
});
