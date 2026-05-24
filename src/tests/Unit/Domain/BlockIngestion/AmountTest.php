<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BlockIngestion;

use App\Modules\BlockIngestion\Domain\ValueObject\Amount;

it('accepts arbitrary-precision integer strings', function (): void {
    $a = new Amount('999999999999999999999999999999');
    expect((string) $a)->toBe('999999999999999999999999999999');
});

it('normalises leading zeros and explicit plus', function (): void {
    expect((string) new Amount('+0042'))->toBe('42');
    expect((string) new Amount('0'))->toBe('0');
    expect((string) new Amount('0000'))->toBe('0');
});

it('rejects negative values and non-digits', function (string $bad): void {
    new Amount($bad);
})->with(['-1', '12.5', '0x10', '', ' 100 '])->throws(\InvalidArgumentException::class);

it('distinguishes zero', function (): void {
    expect(Amount::zero()->isZero())->toBeTrue();
    expect((new Amount('1'))->isZero())->toBeFalse();
});
