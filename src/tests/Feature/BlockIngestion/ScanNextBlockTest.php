<?php

declare(strict_types=1);

namespace Tests\Feature\BlockIngestion;

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
use App\Modules\BlockIngestion\Domain\ValueObject\Amount;
use App\Modules\BlockIngestion\Domain\ValueObject\Currency;
use App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainAction;
use App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainData;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BlockIngestion\Support\FakeBlockSourceFactory;

uses(RefreshDatabase::class);

function registerBitcoinRegtest(): void
{
    /** @var RegisterChainAction $register */
    $register = app(RegisterChainAction::class);
    $register->handle(new RegisterChainData(
        chainId: 'bitcoin-regtest',
        name: 'Bitcoin Regtest',
        family: ChainFamily::Bitcoin->value,
        currencySymbol: 'BTC',
        currencyDecimals: 8,
        requiredConfirmations: 6,
        maxReorgDepth: 100,
        endpoints: [['url' => 'http://bitcoin-regtest:18443', 'kind' => 'http']],
    ));
}

function fakeBlock(int $height, string $toAddress, string $amountSat): FetchedBlock
{
    $hashOf = fn (int $h): string => str_pad((string) $h, 64, 'a', STR_PAD_LEFT);

    return new FetchedBlock(
        height: new BlockHeight($height),
        hash: new BlockHash($hashOf($height)),
        parentHash: new BlockHash($height <= 1 ? str_repeat('0', 64) : $hashOf($height - 1)),
        timestamp: (new DateTimeImmutable('2026-05-21T10:00:00Z'))->modify("+{$height} seconds"),
        currency: new Currency('BTC'),
        transactions: [
            new FetchedTransaction(
                txHash: new TxHash(sprintf('%064x', 0xb000000000 + $height)),
                fromAddress: null,
                outputs: [
                    new FetchedOutput(toAddress: $toAddress, amount: new Amount($amountSat)),
                ],
            ),
        ],
    );
}

it('ingests pending blocks and persists watched recipients', function (): void {
    registerBitcoinRegtest();

    $fake = new FakeBlockSourceFactory();
    $this->app->instance(BlockSourceFactory::class, $fake);

    /** @var AddressDirectory $directory */
    $directory = app(AddressDirectory::class);
    $directory->register(ChainFamily::Bitcoin, 'bcrt1q-watched-address');

    $fake->setHead(10);
    app(InitChainCursorAction::class)->handle(new InitChainCursorData('bitcoin-regtest'));

    $fake->pushBlock(fakeBlock(11, 'bcrt1q-not-ours', '99'));
    $fake->pushBlock(fakeBlock(12, 'bcrt1q-watched-address', '50000000'));

    $result = app(ScanNextBlockAction::class)
        ->handle(new ScanNextBlockData('bitcoin-regtest'));

    expect($result->blocksScanned)->toBe(2);
    expect($result->matchesDetected)->toBe(1);
    expect($result->lastScannedHeight)->toBe(12);

    /** @var IncomingTransactionRepository $incoming */
    $incoming = app(IncomingTransactionRepository::class);
    $rows = $incoming->findByChain(new ChainId('bitcoin-regtest'));
    expect($rows)->toHaveCount(1);
    expect($rows[0]->toAddress)->toBe('bcrt1q-watched-address');
    expect($rows[0]->amount->value)->toBe('50000000');
});

it('is idempotent on the same recipient across two scans', function (): void {
    registerBitcoinRegtest();

    $fake = new FakeBlockSourceFactory();
    $this->app->instance(BlockSourceFactory::class, $fake);

    /** @var AddressDirectory $directory */
    $directory = app(AddressDirectory::class);
    $directory->register(ChainFamily::Bitcoin, 'bcrt1q-watched');

    $fake->setHead(0);
    app(InitChainCursorAction::class)->handle(new InitChainCursorData('bitcoin-regtest'));

    $fake->pushBlock(fakeBlock(1, 'bcrt1q-watched', '5'));

    app(ScanNextBlockAction::class)->handle(new ScanNextBlockData('bitcoin-regtest'));
    app(ScanNextBlockAction::class)->handle(new ScanNextBlockData('bitcoin-regtest'));

    /** @var IncomingTransactionRepository $incoming */
    $incoming = app(IncomingTransactionRepository::class);
    expect($incoming->findByChain(new ChainId('bitcoin-regtest')))->toHaveCount(1);
});

it('refuses to scan when no cursor has been initialised', function (): void {
    registerBitcoinRegtest();
    $fake = new FakeBlockSourceFactory();
    $this->app->instance(BlockSourceFactory::class, $fake);

    app(ScanNextBlockAction::class)->handle(new ScanNextBlockData('bitcoin-regtest'));
})->throws(\App\Modules\BlockIngestion\Domain\Exception\ScanCursorMissingException::class);
