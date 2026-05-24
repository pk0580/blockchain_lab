<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Ledger;

use App\Modules\Ledger\Domain\Entity\LedgerEntry;
use App\Modules\Ledger\Domain\Repository\LedgerEntryRepository;
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

function deposit(string $entryId, string $walletId, int $blockHeight, string $opRef): LedgerEntry
{
    return LedgerEntry::recordDeposit(
        id: new LedgerEntryId($entryId),
        walletId: new WalletId($walletId),
        chainId: new ChainId('bitcoin-regtest'),
        money: new Money('1000', 'BTC'),
        operationRef: new OperationRef($opRef),
        relatedTxHash: new TxHash(str_repeat('a', 64)),
        blockHeight: $blockHeight,
        now: new DateTimeImmutable('2026-05-22T10:00:00Z'),
    );
}

it('round-trips a credit entry through Eloquent', function (): void {
    /** @var LedgerEntryRepository $repo */
    $repo = app(LedgerEntryRepository::class);

    $entry = deposit(
        '11111111-1111-1111-1111-111111111111',
        '22222222-2222-2222-2222-222222222222',
        100,
        'tx-1',
    );
    $repo->save($entry);

    $listed = $repo->listByWallet(new WalletId('22222222-2222-2222-2222-222222222222'));
    expect($listed)->toHaveCount(1);
    expect($listed[0]->direction)->toBe(Direction::Credit);
    expect($listed[0]->status())->toBe(EntryStatus::Confirmed);
    expect($listed[0]->money->amount)->toBe('1000');
});

it('detects existing operation by type+ref', function (): void {
    /** @var LedgerEntryRepository $repo */
    $repo = app(LedgerEntryRepository::class);

    $repo->save(deposit(
        '11111111-1111-1111-1111-111111111111',
        '22222222-2222-2222-2222-222222222222',
        100,
        'tx-1',
    ));

    expect($repo->existsForOperation(OperationType::Deposit, new OperationRef('tx-1')))->toBeTrue();
    expect($repo->existsForOperation(OperationType::Deposit, new OperationRef('tx-2')))->toBeFalse();
    expect($repo->existsForOperation(OperationType::ReorgReversal, new OperationRef('tx-1')))->toBeFalse();
});

it('returns confirmed credits at or above a reorg height', function (): void {
    /** @var LedgerEntryRepository $repo */
    $repo = app(LedgerEntryRepository::class);

    $repo->save(deposit('11111111-1111-1111-1111-111111111111', '22222222-2222-2222-2222-222222222222', 90, 'tx-a'));
    $repo->save(deposit('22221111-1111-1111-1111-111111111111', '22222222-2222-2222-2222-222222222222', 100, 'tx-b'));
    $repo->save(deposit('33331111-1111-1111-1111-111111111111', '22222222-2222-2222-2222-222222222222', 110, 'tx-c'));

    $affected = $repo->findCreditsAffectedByReorg(new ChainId('bitcoin-regtest'), 100);
    expect($affected)->toHaveCount(2);
    expect(array_map(fn ($e) => $e->blockHeight, $affected))->toBe([100, 110]);
});
