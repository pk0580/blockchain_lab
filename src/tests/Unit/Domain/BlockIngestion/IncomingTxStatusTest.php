<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BlockIngestion;

use App\Modules\BlockIngestion\Domain\Exception\InvalidStatusTransitionException;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTxStatus;

it('allows forward confirmation transitions', function (): void {
    expect(IncomingTxStatus::Detected->canTransitionTo(IncomingTxStatus::Confirming))->toBeTrue();
    expect(IncomingTxStatus::Detected->canTransitionTo(IncomingTxStatus::Confirmed))->toBeTrue();
    expect(IncomingTxStatus::Confirming->canTransitionTo(IncomingTxStatus::Confirmed))->toBeTrue();
    expect(IncomingTxStatus::Confirmed->canTransitionTo(IncomingTxStatus::Finalized))->toBeTrue();
});

it('allows reorg transitions from any non-terminal state', function (): void {
    expect(IncomingTxStatus::Detected->canTransitionTo(IncomingTxStatus::Orphaned))->toBeTrue();
    expect(IncomingTxStatus::Confirming->canTransitionTo(IncomingTxStatus::Orphaned))->toBeTrue();
    expect(IncomingTxStatus::Confirmed->canTransitionTo(IncomingTxStatus::Orphaned))->toBeTrue();
});

it('allows re-detection out of orphaned', function (): void {
    expect(IncomingTxStatus::Orphaned->canTransitionTo(IncomingTxStatus::Detected))->toBeTrue();
    expect(IncomingTxStatus::Orphaned->canTransitionTo(IncomingTxStatus::Confirming))->toBeTrue();
    expect(IncomingTxStatus::Orphaned->canTransitionTo(IncomingTxStatus::Confirmed))->toBeTrue();
});

it('allows self transitions', function (): void {
    foreach (IncomingTxStatus::cases() as $status) {
        expect($status->canTransitionTo($status))->toBeTrue();
    }
});

it('forbids regression and finalized escape', function (): void {
    expect(IncomingTxStatus::Confirming->canTransitionTo(IncomingTxStatus::Detected))->toBeFalse();
    expect(IncomingTxStatus::Confirmed->canTransitionTo(IncomingTxStatus::Confirming))->toBeFalse();
    expect(IncomingTxStatus::Confirmed->canTransitionTo(IncomingTxStatus::Detected))->toBeFalse();
    expect(IncomingTxStatus::Finalized->canTransitionTo(IncomingTxStatus::Confirmed))->toBeFalse();
    expect(IncomingTxStatus::Finalized->canTransitionTo(IncomingTxStatus::Orphaned))->toBeFalse();
});

it('throws on assert when transition is invalid', function (): void {
    IncomingTxStatus::Confirmed->assertCanTransitionTo(IncomingTxStatus::Detected);
})->throws(InvalidStatusTransitionException::class);

it('marks finalized as the only terminal state', function (): void {
    expect(IncomingTxStatus::Finalized->isTerminal())->toBeTrue();
    foreach ([IncomingTxStatus::Detected, IncomingTxStatus::Confirming, IncomingTxStatus::Confirmed, IncomingTxStatus::Orphaned] as $status) {
        expect($status->isTerminal())->toBeFalse();
    }
});

it('classifies pending vs non-pending', function (): void {
    expect(IncomingTxStatus::Detected->isPending())->toBeTrue();
    expect(IncomingTxStatus::Confirming->isPending())->toBeTrue();
    expect(IncomingTxStatus::Confirmed->isPending())->toBeTrue();
    expect(IncomingTxStatus::Finalized->isPending())->toBeFalse();
    expect(IncomingTxStatus::Orphaned->isPending())->toBeFalse();
});
