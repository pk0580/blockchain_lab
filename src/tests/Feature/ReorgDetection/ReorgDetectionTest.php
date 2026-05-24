<?php

declare(strict_types=1);

namespace Tests\Feature\ReorgDetection;

use App\Modules\BlockIngestion\Application\UseCase\InitChainCursor\InitChainCursorAction;
use App\Modules\BlockIngestion\Application\UseCase\InitChainCursor\InitChainCursorData;
use App\Modules\BlockIngestion\Application\UseCase\ScanNextBlock\ScanNextBlockAction;
use App\Modules\BlockIngestion\Application\UseCase\ScanNextBlock\ScanNextBlockData;
use App\Modules\BlockIngestion\Domain\Contract\AddressDirectory;
use App\Modules\BlockIngestion\Domain\Contract\BlockSourceFactory;
use App\Modules\BlockIngestion\Domain\ReadModel\FetchedBlock;
use App\Modules\BlockIngestion\Domain\ReadModel\FetchedOutput;
use App\Modules\BlockIngestion\Domain\ReadModel\FetchedTransaction;
use App\Modules\BlockIngestion\Domain\Repository\IncomingTransactionRepository;
use App\Modules\BlockIngestion\Domain\Repository\ScanCursorRepository;
use App\Modules\BlockIngestion\Domain\ValueObject\Amount;
use App\Modules\BlockIngestion\Domain\ValueObject\Currency;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTxStatus;
use App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainAction;
use App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainData;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use App\Modules\ReorgDetection\Domain\Event\ReorgDetected;
use App\Modules\ReorgDetection\Infrastructure\Persistence\Eloquent\Models\BlockReadModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Feature\BlockIngestion\Support\FakeBlockSourceFactory;

uses(RefreshDatabase::class);

function chainAndFakeSource(FakeBlockSourceFactory $fake): void
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
    app()->instance(BlockSourceFactory::class, $fake);
}

function reorgFakeBlock(int $height, string $hashChar, string $parentChar, string $toAddress): FetchedBlock
{
    return new FetchedBlock(
        height: new BlockHeight($height),
        hash: new BlockHash(str_repeat($hashChar, 64)),
        parentHash: new BlockHash(str_repeat($parentChar, 64)),
        timestamp: (new DateTimeImmutable('2026-05-22T10:00:00Z'))->modify("+{$height} seconds"),
        currency: new Currency('BTC'),
        transactions: [
            new FetchedTransaction(
                txHash: new TxHash(sprintf('%064x', 0xd000000000 + $height)),
                fromAddress: null,
                outputs: [
                    new FetchedOutput(toAddress: $toAddress, amount: new Amount('1000')),
                ],
            ),
        ],
    );
}

it('orphans a block when parent_hash diverges from stored chain', function (): void {
    Event::fake([ReorgDetected::class]);

    $fake = new FakeBlockSourceFactory();
    chainAndFakeSource($fake);

    /** @var AddressDirectory $directory */
    $directory = app(AddressDirectory::class);
    $directory->register(ChainFamily::Bitcoin, 'watched-addr');

    // Init: head=5, baseline (cursor=5).
    $fake->setHead(5);
    app(InitChainCursorAction::class)->handle(new InitChainCursorData('bitcoin-regtest'));

    // Mine chain A: blocks 6 → A6, 7 → A7 (parent A6).
    $fake->pushBlock(reorgFakeBlock(6, 'a', '5', 'addr-not-ours'));
    $fake->pushBlock(reorgFakeBlock(7, 'b', 'a', 'watched-addr'));
    app(ScanNextBlockAction::class)->handle(new ScanNextBlockData('bitcoin-regtest'));

    /** @var IncomingTransactionRepository $incoming */
    $incoming = app(IncomingTransactionRepository::class);
    $rows = $incoming->findByChain(new ChainId('bitcoin-regtest'));
    expect($rows)->toHaveCount(1);
    expect($rows[0]->status())->toBe(IncomingTxStatus::Detected);
    expect($rows[0]->blockHeight?->value)->toBe(7);

    // Reorg: новый блок 8 имеет parent != hash(A7) (хеш-символ 'b' vs 'd').
    $fake->pushBlock(reorgFakeBlock(8, 'c', 'd', 'addr-not-ours'));
    app(ScanNextBlockAction::class)->handle(new ScanNextBlockData('bitcoin-regtest'));

    // Stored block 7 удалён, ингесченная транзакция на нём → orphaned, cursor=6.
    expect(BlockReadModel::query()->where('chain_id', 'bitcoin-regtest')->where('height', 7)->exists())->toBeFalse();
    expect(BlockReadModel::query()->where('chain_id', 'bitcoin-regtest')->where('height', 8)->exists())->toBeTrue();

    $rows = $incoming->findByChain(new ChainId('bitcoin-regtest'));
    expect($rows[0]->status())->toBe(IncomingTxStatus::Orphaned);
    expect($rows[0]->confirmations())->toBe(0);

    /** @var ScanCursorRepository $cursors */
    $cursors = app(ScanCursorRepository::class);
    $cursor = $cursors->findByChain(new ChainId('bitcoin-regtest'));
    expect($cursor?->lastScannedHeight()->value)->toBe(6);

    Event::assertDispatched(ReorgDetected::class, function (ReorgDetected $event): bool {
        return $event->orphanedHeight->value === 7
            && $event->orphanedTransactionCount === 1
            && $event->chainId->value === 'bitcoin-regtest';
    });
});

it('emits no reorg event when stored prev matches the new block parent', function (): void {
    Event::fake([ReorgDetected::class]);

    $fake = new FakeBlockSourceFactory();
    chainAndFakeSource($fake);

    $fake->setHead(5);
    app(InitChainCursorAction::class)->handle(new InitChainCursorData('bitcoin-regtest'));

    $fake->pushBlock(reorgFakeBlock(6, 'a', '5', 'addr-not-ours'));
    $fake->pushBlock(reorgFakeBlock(7, 'b', 'a', 'addr-not-ours'));
    app(ScanNextBlockAction::class)->handle(new ScanNextBlockData('bitcoin-regtest'));

    Event::assertNotDispatched(ReorgDetected::class);
});
