<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ledger;

use App\Modules\Ledger\Domain\Entity\LedgerEntry;
use App\Modules\Ledger\Domain\Event\LedgerEntryRecorded;
use App\Modules\Ledger\Domain\Event\LedgerEntryReversed;
use App\Modules\Ledger\Domain\ValueObject\Direction;
use App\Modules\Ledger\Domain\ValueObject\EntryStatus;
use App\Modules\Ledger\Domain\ValueObject\LedgerEntryId;
use App\Modules\Ledger\Domain\ValueObject\Money;
use App\Modules\Ledger\Domain\ValueObject\OperationRef;
use App\Modules\Ledger\Domain\ValueObject\OperationType;
use App\Modules\Ledger\Domain\ValueObject\WalletId;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use DateTimeImmutable;

function makeDeposit(string $entryId, string $walletId, string $amount = '50000000'): LedgerEntry
{
    return LedgerEntry::recordDeposit(
        id: new LedgerEntryId($entryId),
        walletId: new WalletId($walletId),
        chainId: new ChainId('bitcoin-regtest'),
        money: new Money($amount, 'BTC'),
        operationRef: new OperationRef('tx-1'),
        relatedTxHash: new TxHash(str_repeat('a', 64)),
        blockHeight: 105,
        now: new DateTimeImmutable('2026-05-22T10:00:00Z'),
    );
}

it('records a deposit as a confirmed credit entry', function (): void {
    $entry = makeDeposit(
        '11111111-1111-1111-1111-111111111111',
        '22222222-2222-2222-2222-222222222222',
    );

    expect($entry->direction)->toBe(Direction::Credit);
    expect($entry->operationType)->toBe(OperationType::Deposit);
    expect($entry->status())->toBe(EntryStatus::Confirmed);
    expect($entry->reversesEntryId)->toBeNull();

    $events = $entry->pullPendingEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(LedgerEntryRecorded::class);
});

it('creates an opposite debit when reversing a credit', function (): void {
    $original = makeDeposit(
        '11111111-1111-1111-1111-111111111111',
        '22222222-2222-2222-2222-222222222222',
    );
    $original->pullPendingEvents();

    $reversal = LedgerEntry::reverse(
        id: new LedgerEntryId('33333333-3333-3333-3333-333333333333'),
        original: $original,
        operationRef: new OperationRef('reorg:'.$original->id->value),
        now: new DateTimeImmutable('2026-05-22T11:00:00Z'),
    );

    expect($reversal->direction)->toBe(Direction::Debit);
    expect($reversal->operationType)->toBe(OperationType::ReorgReversal);
    expect($reversal->reversesEntryId?->value)->toBe($original->id->value);
    expect($reversal->money->amount)->toBe('50000000');

    expect($original->status())->toBe(EntryStatus::Reversed);

    $events = $reversal->pullPendingEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(LedgerEntryReversed::class);
});

it('refuses to reverse an entry that is already reversed', function (): void {
    $original = makeDeposit(
        '11111111-1111-1111-1111-111111111111',
        '22222222-2222-2222-2222-222222222222',
    );
    $original->markReversed();

    LedgerEntry::reverse(
        id: new LedgerEntryId('33333333-3333-3333-3333-333333333333'),
        original: $original,
        operationRef: new OperationRef('reorg:'.$original->id->value),
        now: new DateTimeImmutable('2026-05-22T11:00:00Z'),
    );
})->throws(\DomainException::class);
