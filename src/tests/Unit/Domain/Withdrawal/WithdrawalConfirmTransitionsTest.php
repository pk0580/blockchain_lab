<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Withdrawal;

use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Withdrawal\Domain\Entity\Withdrawal;
use App\Modules\Withdrawal\Domain\Event\WithdrawalConfirmed;
use App\Modules\Withdrawal\Domain\Event\WithdrawalConfirming;
use App\Modules\Withdrawal\Domain\Event\WithdrawalReplaced;
use App\Modules\Withdrawal\Domain\Event\WithdrawalStuck;
use App\Modules\Withdrawal\Domain\Exception\InvalidWithdrawalStateTransitionException;
use App\Modules\Withdrawal\Domain\ValueObject\Currency;
use App\Modules\Withdrawal\Domain\ValueObject\FeeQuoteSnapshot;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\WalletId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalAmount;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;
use DateTimeImmutable;

function makeBroadcastedWithdrawal(): Withdrawal
{
    $w = Withdrawal::request(
        id: new WithdrawalId('11111111-1111-4111-8111-111111111122'),
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
        idempotencyKey: new IdempotencyKey('idem-test-confirm'),
        now: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    );
    $now = new DateTimeImmutable('2026-01-01T00:01:00Z');
    $w->markAsBuilt('deadbeef', null, ['inputs' => [['txid' => 'aa', 'vout' => 0, 'amount_sat' => 100000]]], $now);
    $w->markAsSigned('cafebabe', $now);
    $w->markAsBroadcasted(new TxHash(str_repeat('b', 64)), $now);
    $w->pullPendingEvents();
    return $w;
}

it('walks Broadcasted → Confirming → Confirmed and emits matching events', function (): void {
    $w = makeBroadcastedWithdrawal();
    $now = new DateTimeImmutable('2026-01-01T00:05:00Z');

    $w->markAsConfirming(2, $now);
    expect($w->status())->toBe(WithdrawalStatus::Confirming);
    expect($w->confirmations())->toBe(2);
    expect($w->pullPendingEvents()[0])->toBeInstanceOf(WithdrawalConfirming::class);

    $w->markAsConfirmed(6, $now);
    expect($w->status())->toBe(WithdrawalStatus::Confirmed);
    expect($w->confirmations())->toBe(6);
    expect($w->confirmedAt())->not->toBeNull();
    expect($w->pullPendingEvents()[0])->toBeInstanceOf(WithdrawalConfirmed::class);
});

it('does not emit a Confirming event when count is unchanged', function (): void {
    $w = makeBroadcastedWithdrawal();
    $now = new DateTimeImmutable('2026-01-01T00:05:00Z');

    $w->markAsConfirming(3, $now);
    $w->pullPendingEvents();
    $w->markAsConfirming(3, $now);

    expect($w->pullPendingEvents())->toBe([]);
});

it('moves from Broadcasted to Stuck and emits WithdrawalStuck', function (): void {
    $w = makeBroadcastedWithdrawal();
    $now = new DateTimeImmutable('2026-01-01T00:30:00Z');

    $w->markAsStuck($now);
    expect($w->status())->toBe(WithdrawalStatus::Stuck);

    /** @var WithdrawalStuck $evt */
    $evt = $w->pullPendingEvents()[0];
    expect($evt)->toBeInstanceOf(WithdrawalStuck::class);
    expect($evt->chainId->value)->toBe('bitcoin-regtest');
});

it('moves Stuck → Replaced with a replacement id', function (): void {
    $w = makeBroadcastedWithdrawal();
    $now = new DateTimeImmutable('2026-01-01T00:30:00Z');

    $w->markAsStuck($now);
    $w->pullPendingEvents();

    $replacementId = new WithdrawalId('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
    $w->markAsReplaced($replacementId, $now);

    expect($w->status())->toBe(WithdrawalStatus::Replaced);

    /** @var WithdrawalReplaced $evt */
    $evt = $w->pullPendingEvents()[0];
    expect($evt)->toBeInstanceOf(WithdrawalReplaced::class);
    expect($evt->replacementId->value)->toBe($replacementId->value);
});

it('forbids Confirmed → Built', function (): void {
    $w = makeBroadcastedWithdrawal();
    $now = new DateTimeImmutable();
    $w->markAsConfirmed(6, $now);
    expect(fn () => $w->markAsBuilt('xx', null, null, $now))
        ->toThrow(InvalidWithdrawalStateTransitionException::class);
});
