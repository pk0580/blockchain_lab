<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Confirmation;

use App\Modules\Confirmation\Domain\Service\ConfirmationCalculator;
use App\Modules\Confirmation\Domain\ValueObject\ConfirmationOutcome;

it('counts confirmations inclusive of the tx block', function (): void {
    $calc = new ConfirmationCalculator();
    $step = $calc->compute(
        txBlockHeight: 100,
        lastScannedHeight: 100,
        requiredConfirmations: 6,
        maxReorgDepth: 100,
    );
    expect($step->confirmations)->toBe(1);
    expect($step->outcome)->toBe(ConfirmationOutcome::Confirming);
});

it('flips to confirmed at exactly required confirmations', function (): void {
    $calc = new ConfirmationCalculator();
    $step = $calc->compute(
        txBlockHeight: 100,
        lastScannedHeight: 105,
        requiredConfirmations: 6,
        maxReorgDepth: 100,
    );
    expect($step->confirmations)->toBe(6);
    expect($step->outcome)->toBe(ConfirmationOutcome::Confirmed);
});

it('flips to finalized when confirmations exceed max reorg depth', function (): void {
    $calc = new ConfirmationCalculator();
    $step = $calc->compute(
        txBlockHeight: 100,
        lastScannedHeight: 207,
        requiredConfirmations: 6,
        maxReorgDepth: 100,
    );
    expect($step->confirmations)->toBe(108);
    expect($step->outcome)->toBe(ConfirmationOutcome::Finalized);
});

it('stays confirmed at exactly max reorg depth', function (): void {
    $calc = new ConfirmationCalculator();
    $step = $calc->compute(
        txBlockHeight: 100,
        lastScannedHeight: 199,
        requiredConfirmations: 6,
        maxReorgDepth: 100,
    );
    expect($step->confirmations)->toBe(100);
    expect($step->outcome)->toBe(ConfirmationOutcome::Confirmed);
});

it('clamps at zero when scanner is behind the tx block', function (): void {
    $calc = new ConfirmationCalculator();
    $step = $calc->compute(
        txBlockHeight: 200,
        lastScannedHeight: 100,
        requiredConfirmations: 6,
        maxReorgDepth: 100,
    );
    expect($step->confirmations)->toBe(0);
    expect($step->outcome)->toBe(ConfirmationOutcome::Confirming);
});

it('refuses required confirmations below 1', function (): void {
    (new ConfirmationCalculator())->compute(1, 1, 0, 1);
})->throws(\InvalidArgumentException::class);

it('refuses max reorg depth smaller than required confirmations', function (): void {
    (new ConfirmationCalculator())->compute(1, 1, 6, 3);
})->throws(\InvalidArgumentException::class);
