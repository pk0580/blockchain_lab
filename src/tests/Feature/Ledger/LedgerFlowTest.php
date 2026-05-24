<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Modules\Confirmation\Domain\Event\TransactionConfirmed;
use App\Modules\Ledger\Domain\Repository\LedgerEntryRepository;
use App\Modules\Ledger\Domain\ValueObject\Direction;
use App\Modules\Ledger\Domain\ValueObject\EntryStatus;
use App\Modules\Ledger\Domain\ValueObject\OperationType;
use App\Modules\Ledger\Domain\ValueObject\WalletId;
use App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Models\IncomingTransactionLookupModel;
use App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainData;
use App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainAction;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\ReorgDetection\Domain\Event\ReorgDetected;
use App\Modules\ReorgDetection\Domain\ValueObject\ReorgDepth;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function ledgerRegisterChain(): void
{
    app(RegisterChainAction::class)->handle(new RegisterChainData(
        chainId: 'bitcoin-regtest',
        name: 'Bitcoin Regtest',
        family: ChainFamily::Bitcoin->value,
        currencySymbol: 'BTC',
        currencyDecimals: 8,
        requiredConfirmations: 3,
        maxReorgDepth: 10,
        endpoints: [['url' => 'http://bitcoin-regtest:18443', 'kind' => 'http']],
    ));
}

function insertAddress(string $walletId, string $family, string $address): void
{
    $seedId = Str::uuid()->toString();
    DB::table('hd_seeds')->insert([
        'id' => $seedId,
        'reference' => 'ref-'.substr($seedId, 0, 8),
        'family' => $family,
        'created_at' => '2026-05-22 10:00:00+00',
    ]);
    DB::table('addresses')->insert([
        'id' => Str::uuid()->toString(),
        'hd_seed_id' => $seedId,
        'family' => $family,
        'address' => $address,
        'derivation_path' => "m/44'/0'/0'/0/0",
        'derivation_index' => 0,
        'wallet_id' => $walletId,
        'created_at' => '2026-05-22 10:00:00+00',
    ]);
}

function insertConfirmedIncomingTransaction(string $id, int $blockHeight, string $toAddress, string $amount): void
{
    DB::table('incoming_transactions')->insert([
        'id' => $id,
        'chain_id' => 'bitcoin-regtest',
        'tx_hash' => sprintf('%064x', 0xfeedf00d + $blockHeight),
        'block_height' => $blockHeight,
        'block_hash' => str_repeat('e', 64),
        'from_address' => null,
        'to_address' => $toAddress,
        'amount' => $amount,
        'currency' => 'BTC',
        'status' => 'confirmed',
        'confirmations' => 6,
        'detected_at' => '2026-05-22 10:00:00+00',
    ]);
}

it('records a credit when a transaction is confirmed', function (): void {
    Event::fake([\App\Modules\Ledger\Domain\Event\LedgerEntryRecorded::class]);
    ledgerRegisterChain();

    $walletId = Str::uuid()->toString();
    insertAddress($walletId, ChainFamily::Bitcoin->value, 'bcrt1q-wallet-addr');

    $txId = Str::uuid()->toString();
    insertConfirmedIncomingTransaction($txId, 100, 'bcrt1q-wallet-addr', '50000000');

    event(new TransactionConfirmed(
        incomingTransactionId: $txId,
        chainId: new ChainId('bitcoin-regtest'),
        confirmations: 6,
        occurredAt: new DateTimeImmutable('2026-05-22T11:00:00Z'),
    ));

    /** @var LedgerEntryRepository $repo */
    $repo = app(LedgerEntryRepository::class);
    $entries = $repo->listByWallet(new WalletId($walletId));

    expect($entries)->toHaveCount(1);
    expect($entries[0]->direction)->toBe(Direction::Credit);
    expect($entries[0]->operationType)->toBe(OperationType::Deposit);
    expect($entries[0]->status())->toBe(EntryStatus::Confirmed);
    expect($entries[0]->money->amount)->toBe('50000000');

    Event::assertDispatched(\App\Modules\Ledger\Domain\Event\LedgerEntryRecorded::class);
});

it('is idempotent on a repeated TransactionConfirmed event', function (): void {
    ledgerRegisterChain();
    $walletId = Str::uuid()->toString();
    insertAddress($walletId, ChainFamily::Bitcoin->value, 'bcrt1q-wallet-addr');
    $txId = Str::uuid()->toString();
    insertConfirmedIncomingTransaction($txId, 101, 'bcrt1q-wallet-addr', '99');

    $event = new TransactionConfirmed(
        incomingTransactionId: $txId,
        chainId: new ChainId('bitcoin-regtest'),
        confirmations: 6,
        occurredAt: new DateTimeImmutable('2026-05-22T11:00:00Z'),
    );
    event($event);
    event($event);

    /** @var LedgerEntryRepository $repo */
    $repo = app(LedgerEntryRepository::class);
    expect($repo->listByWallet(new WalletId($walletId)))->toHaveCount(1);
});

it('writes a reversal entry and marks the original Reversed on ReorgDetected', function (): void {
    Event::fake([\App\Modules\Ledger\Domain\Event\LedgerEntryReversed::class]);
    ledgerRegisterChain();
    $walletId = Str::uuid()->toString();
    insertAddress($walletId, ChainFamily::Bitcoin->value, 'bcrt1q-wallet-addr');

    $txId = Str::uuid()->toString();
    insertConfirmedIncomingTransaction($txId, 100, 'bcrt1q-wallet-addr', '70000');

    event(new TransactionConfirmed(
        incomingTransactionId: $txId,
        chainId: new ChainId('bitcoin-regtest'),
        confirmations: 6,
        occurredAt: new DateTimeImmutable('2026-05-22T11:00:00Z'),
    ));

    event(new ReorgDetected(
        chainId: new ChainId('bitcoin-regtest'),
        orphanedHeight: new BlockHeight(100),
        depth: new ReorgDepth(1),
        orphanedTransactionCount: 1,
        occurredAt: new DateTimeImmutable('2026-05-22T12:00:00Z'),
    ));

    /** @var LedgerEntryRepository $repo */
    $repo = app(LedgerEntryRepository::class);
    $entries = $repo->listByWallet(new WalletId($walletId));

    expect($entries)->toHaveCount(2);

    $credit = collect($entries)->firstWhere(fn ($e) => $e->direction === Direction::Credit);
    $debit = collect($entries)->firstWhere(fn ($e) => $e->direction === Direction::Debit);

    expect($credit)->not->toBeNull();
    expect($credit->status())->toBe(EntryStatus::Reversed);

    expect($debit)->not->toBeNull();
    expect($debit->status())->toBe(EntryStatus::Confirmed);
    expect($debit->operationType)->toBe(OperationType::ReorgReversal);
    expect($debit->money->amount)->toBe('70000');
    expect($debit->reversesEntryId?->value)->toBe($credit->id->value);

    Event::assertDispatched(\App\Modules\Ledger\Domain\Event\LedgerEntryReversed::class);
});

it('does not touch ledger when reorg affects only blocks without confirmed credits', function (): void {
    ledgerRegisterChain();
    $walletId = Str::uuid()->toString();
    insertAddress($walletId, ChainFamily::Bitcoin->value, 'bcrt1q-wallet-addr');

    $txId = Str::uuid()->toString();
    insertConfirmedIncomingTransaction($txId, 100, 'bcrt1q-wallet-addr', '70000');
    event(new TransactionConfirmed(
        incomingTransactionId: $txId,
        chainId: new ChainId('bitcoin-regtest'),
        confirmations: 6,
        occurredAt: new DateTimeImmutable('2026-05-22T11:00:00Z'),
    ));

    // Reorg на блок выше нашего credit — credit не затронут.
    event(new ReorgDetected(
        chainId: new ChainId('bitcoin-regtest'),
        orphanedHeight: new BlockHeight(150),
        depth: new ReorgDepth(1),
        orphanedTransactionCount: 0,
        occurredAt: new DateTimeImmutable('2026-05-22T12:00:00Z'),
    ));

    /** @var LedgerEntryRepository $repo */
    $repo = app(LedgerEntryRepository::class);
    $entries = $repo->listByWallet(new WalletId($walletId));

    expect($entries)->toHaveCount(1);
    expect($entries[0]->status())->toBe(EntryStatus::Confirmed);
});
