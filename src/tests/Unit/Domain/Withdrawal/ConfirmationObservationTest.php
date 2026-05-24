<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Withdrawal;

use App\Modules\Withdrawal\Domain\ValueObject\ConfirmationObservation;
use InvalidArgumentException;

it('captures a pending observation', function (): void {
    $o = ConfirmationObservation::pending();
    expect($o->confirmations)->toBe(0);
    expect($o->dropped)->toBeFalse();
    expect($o->isPending())->toBeTrue();
});

it('captures a confirmed observation', function (): void {
    $o = ConfirmationObservation::confirmed(6);
    expect($o->confirmations)->toBe(6);
    expect($o->dropped)->toBeFalse();
    expect($o->isPending())->toBeFalse();
});

it('captures a dropped observation', function (): void {
    $o = ConfirmationObservation::dropped();
    expect($o->confirmations)->toBe(0);
    expect($o->dropped)->toBeTrue();
    expect($o->isPending())->toBeFalse();
});

it('rejects negative confirmations', function (): void {
    expect(fn () => new ConfirmationObservation(-1, false))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects dropped with confirmations', function (): void {
    expect(fn () => new ConfirmationObservation(3, true))
        ->toThrow(InvalidArgumentException::class);
});
