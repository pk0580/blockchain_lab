<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Withdrawal;

use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Withdrawal\Domain\Entity\Withdrawal;
use App\Modules\Withdrawal\Domain\Event\WithdrawalBroadcasted;
use App\Modules\Withdrawal\Domain\Event\WithdrawalBuilt;
use App\Modules\Withdrawal\Domain\Event\WithdrawalFailed;
use App\Modules\Withdrawal\Domain\Event\WithdrawalRequested;
use App\Modules\Withdrawal\Domain\Event\WithdrawalSigned;
use App\Modules\Withdrawal\Domain\Exception\InvalidWithdrawalStateTransitionException;
use App\Modules\Withdrawal\Domain\ValueObject\Currency;
use App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;
use App\Modules\Withdrawal\Domain\ValueObject\WalletId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;
use DateTimeImmutable;

function makeBtcWithdrawal(): Withdrawal
{
    return Withdrawal::request(
        id: new WithdrawalId('11111111-1111-4111-8111-111111111111'),
        walletId: new WalletId('w-1'),
        chainId: new ChainId('bitcoin-regtest'),
        hotAddress: new HotAddress('bcrt1qhot'),
        toAddress: new Address('bcrt1qrecipient'),
        amount: new WithdrawalAmount('50000'),
        currency: new Currency('BTC'),
        feeQuote: new FeeQuoteSnapshot(
            priority: 'standard',
            breakdown: ['family' => 'bitcoin', 'sat_per_vbyte' => 5],
            estimatedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        ),
        idempotencyKey: new IdempotencyKey('key-abc123'),
        now: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    );
}

it('starts in Requested status and emits WithdrawalRequested', function (): void {
    $w = makeBtcWithdrawal();

    expect($w->status())->toBe(WithdrawalStatus::Requested);
    expect($w->txHash())->toBeNull();
    expect($w->rawTxHex())->toBeNull();

    $events = $w->pullPendingEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(WithdrawalRequested::class);
    expect($w->pullPendingEvents())->toBe([]);
});

it('walks Requested → Built → Signed → Broadcasted emitting events at each step', function (): void {
    $w = makeBtcWithdrawal();
    $w->pullPendingEvents();
    $now = new DateTimeImmutable('2026-01-01T00:01:00Z');

    $w->markAsBuilt('deadbeef', null, null, $now);
    expect($w->status())->toBe(WithdrawalStatus::Built);
    expect($w->rawTxHex())->toBe('deadbeef');
    expect($w->pullPendingEvents()[0])->toBeInstanceOf(WithdrawalBuilt::class);

    $w->markAsSigned('cafebabe', $now);
    expect($w->status())->toBe(WithdrawalStatus::Signed);
    expect($w->rawTxHex())->toBe('cafebabe');
    expect($w->pullPendingEvents()[0])->toBeInstanceOf(WithdrawalSigned::class);

    $tx = new TxHash(str_repeat('a', 64));
    $w->markAsBroadcasted($tx, $now);
    expect($w->status())->toBe(WithdrawalStatus::Broadcasted);
    expect($w->txHash()?->value)->toBe($tx->value);
    expect($w->broadcastAt())->not->toBeNull();
    expect($w->pullPendingEvents()[0])->toBeInstanceOf(WithdrawalBroadcasted::class);
});

it('captures the EVM nonce on markAsBuilt', function (): void {
    $w = makeBtcWithdrawal();
    $w->markAsBuilt('beefcafe', new NonceValue(7), null, new DateTimeImmutable());
    expect($w->nonce()?->value)->toBe(7);
});

it('forbids skipping the Built stage', function (): void {
    $w = makeBtcWithdrawal();
    expect(fn () => $w->markAsSigned('cafebabe', new DateTimeImmutable()))
        ->toThrow(InvalidWithdrawalStateTransitionException::class);
});

it('moves to Failed from any non-terminal state with a reason', function (): void {
    $w = makeBtcWithdrawal();
    $w->fail('upstream timeout', new DateTimeImmutable());

    expect($w->status())->toBe(WithdrawalStatus::Failed);
    expect($w->failureReason())->toBe('upstream timeout');

    $events = $w->pullPendingEvents();
    /** @var WithdrawalFailed $event */
    $event = $events[array_key_last($events)];
    expect($event)->toBeInstanceOf(WithdrawalFailed::class);
    expect($event->reason)->toBe('upstream timeout');
});

it('rejects a second fail() after terminal state', function (): void {
    $w = makeBtcWithdrawal();
    $w->fail('first', new DateTimeImmutable());
    expect(fn () => $w->fail('second', new DateTimeImmutable()))
        ->toThrow(InvalidWithdrawalStateTransitionException::class);
});
