<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Withdrawal;

use App\Modules\Withdrawal\Domain\Exception\InvalidWithdrawalStateTransitionException;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;

it('allows the happy path Requested → Built → Signed → Broadcasted', function (): void {
    WithdrawalStatus::Requested->assertCanTransitionTo(WithdrawalStatus::Built);
    WithdrawalStatus::Built->assertCanTransitionTo(WithdrawalStatus::Signed);
    WithdrawalStatus::Signed->assertCanTransitionTo(WithdrawalStatus::Broadcasted);
    expect(true)->toBeTrue();
});

it('allows Failed from any non-terminal state', function (): void {
    foreach ([WithdrawalStatus::Requested, WithdrawalStatus::Built, WithdrawalStatus::Signed, WithdrawalStatus::Broadcasted] as $state) {
        $state->assertCanTransitionTo(WithdrawalStatus::Failed);
    }
    expect(true)->toBeTrue();
});

it('rejects reverse transitions Built → Requested', function (): void {
    expect(fn () => WithdrawalStatus::Built->assertCanTransitionTo(WithdrawalStatus::Requested))
        ->toThrow(InvalidWithdrawalStateTransitionException::class);
});

it('rejects skipping steps Requested → Broadcasted', function (): void {
    expect(fn () => WithdrawalStatus::Requested->assertCanTransitionTo(WithdrawalStatus::Broadcasted))
        ->toThrow(InvalidWithdrawalStateTransitionException::class);
});

it('treats Confirmed / Failed / Replaced as terminal — no further transitions', function (): void {
    foreach ([WithdrawalStatus::Confirmed, WithdrawalStatus::Failed, WithdrawalStatus::Replaced] as $state) {
        expect($state->isTerminal())->toBeTrue();
        expect(fn () => $state->assertCanTransitionTo(WithdrawalStatus::Built))
            ->toThrow(InvalidWithdrawalStateTransitionException::class);
    }
});
