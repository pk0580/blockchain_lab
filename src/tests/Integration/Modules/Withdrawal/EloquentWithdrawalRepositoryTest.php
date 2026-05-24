<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Withdrawal;

use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\Withdrawal\Domain\Entity\Withdrawal;
use App\Modules\Withdrawal\Domain\Repository\WithdrawalRepository;
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

function makeFreshWithdrawal(string $id = '22222222-2222-4222-8222-222222222222', string $key = 'idem-fresh-key-1'): Withdrawal
{
    return Withdrawal::request(
        id: new WithdrawalId($id),
        walletId: new WalletId('wallet-x'),
        chainId: new ChainId('ethereum-sepolia'),
        hotAddress: new HotAddress('0xhotwallet'),
        toAddress: new Address('0x000000000000000000000000000000000000dead'),
        amount: new WithdrawalAmount('1000000000000000'),
        currency: new Currency('ETH'),
        feeQuote: new FeeQuoteSnapshot(
            priority: 'standard',
            breakdown: [
                'family' => 'evm',
                'max_fee_per_gas_wei' => '20000000000',
                'max_priority_fee_per_gas_wei' => '1500000000',
                'gas_limit' => 21000,
            ],
            estimatedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        ),
        idempotencyKey: new IdempotencyKey($key),
        now: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    );
}

it('round-trips a freshly requested withdrawal', function (): void {
    /** @var WithdrawalRepository $repo */
    $repo = app(WithdrawalRepository::class);
    $w = makeFreshWithdrawal();
    $repo->save($w);

    $loaded = $repo->findById($w->id);
    expect($loaded)->not->toBeNull();
    expect($loaded?->status())->toBe(WithdrawalStatus::Requested);
    expect($loaded?->amount->value)->toBe('1000000000000000');
    expect($loaded?->feeQuote->family())->toBe('evm');
});

it('finds the row by Idempotency-Key', function (): void {
    /** @var WithdrawalRepository $repo */
    $repo = app(WithdrawalRepository::class);
    $w = makeFreshWithdrawal(key: 'idem-find-by-key');
    $repo->save($w);

    $found = $repo->findByIdempotencyKey(new IdempotencyKey('idem-find-by-key'));
    expect($found?->id->equals($w->id))->toBeTrue();
});

it('persists state transitions: Built/Signed/Broadcasted columns survive a reload', function (): void {
    /** @var WithdrawalRepository $repo */
    $repo = app(WithdrawalRepository::class);
    $w = makeFreshWithdrawal();
    $repo->save($w);

    $now = new DateTimeImmutable('2026-01-01T00:10:00Z');
    $w->markAsBuilt('aabbccdd', new NonceValue(3), ['inputs' => [['txid' => 'aa', 'vout' => 0]]], $now);
    $repo->save($w);
    $w->markAsSigned('0xddccbbaa', $now);
    $repo->save($w);
    $w->markAsBroadcasted(new TxHash('0x'.str_repeat('a', 64)), $now);
    $repo->save($w);

    $loaded = $repo->findById($w->id);
    expect($loaded?->status())->toBe(WithdrawalStatus::Broadcasted);
    expect($loaded?->nonce()?->value)->toBe(3);
    expect($loaded?->txHash()?->value)->toBe('0x'.str_repeat('a', 64));
    expect($loaded?->rawTxHex())->toBe('0xddccbbaa');
    expect($loaded?->broadcastAt()?->format(DATE_ATOM))->toBe($now->format(DATE_ATOM));
    expect($loaded?->signingExtras())->toBe(['inputs' => [['txid' => 'aa', 'vout' => 0]]]);
});

it('returns null for an unknown id or key', function (): void {
    /** @var WithdrawalRepository $repo */
    $repo = app(WithdrawalRepository::class);
    expect($repo->findById(new WithdrawalId('33333333-3333-4333-8333-333333333333')))->toBeNull();
    expect($repo->findByIdempotencyKey(new IdempotencyKey('idem-not-existing-99')))->toBeNull();
});
