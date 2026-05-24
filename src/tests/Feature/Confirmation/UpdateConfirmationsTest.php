<?php

declare(strict_types=1);

namespace Tests\Feature\Confirmation;

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
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTxStatus;
use App\Modules\Confirmation\Application\UseCase\UpdateConfirmations\UpdateConfirmationsAction;
use App\Modules\Confirmation\Application\UseCase\UpdateConfirmations\UpdateConfirmationsData;
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

function setupChainAndCursor(FakeBlockSourceFactory $fake): void
{
    app(RegisterChainAction::class)->handle(new RegisterChainData(
        chainId: 'bitcoin-regtest',
        name: 'Bitcoin Regtest',
        family: ChainFamily::Bitcoin->value,
        currencySymbol: 'BTC',
        currencyDecimals: 8,
        requiredConfirmations: 3,
        maxReorgDepth: 100,
        endpoints: [['url' => 'http://bitcoin-regtest:18443', 'kind' => 'http']],
    ));

    $fake->setHead(0);
    app(InitChainCursorAction::class)->handle(new InitChainCursorData('bitcoin-regtest'));
}

function txInBlock(int $height, string $to): FetchedBlock
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
                txHash: new TxHash(sprintf('%064x', 0xc000000000 + $height)),
                fromAddress: null,
                outputs: [
                    new FetchedOutput(toAddress: $to, amount: new Amount('10000000')),
                ],
            ),
        ],
    );
}

it('promotes a tx from detected → confirming → confirmed as the cursor moves', function (): void {
    $fake = new FakeBlockSourceFactory();
    $this->app->instance(BlockSourceFactory::class, $fake);

    setupChainAndCursor($fake);

    /** @var AddressDirectory $directory */
    $directory = app(AddressDirectory::class);
    $directory->register(ChainFamily::Bitcoin, 'addr-of-interest');

    $fake->pushBlock(txInBlock(1, 'addr-of-interest'));
    app(ScanNextBlockAction::class)->handle(new ScanNextBlockData('bitcoin-regtest'));

    /** @var IncomingTransactionRepository $incoming */
    $incoming = app(IncomingTransactionRepository::class);
    $rows = $incoming->findByChain(new ChainId('bitcoin-regtest'));
    expect($rows[0]->status())->toBe(IncomingTxStatus::Detected);

    // 1 confirmation → confirming.
    $result1 = app(UpdateConfirmationsAction::class)
        ->handle(new UpdateConfirmationsData('bitcoin-regtest'));
    expect($result1->rowsConfirming)->toBe(1);
    expect($result1->rowsConfirmed)->toBe(0);
    expect($result1->rowsFinalized)->toBe(0);

    $rows = $incoming->findByChain(new ChainId('bitcoin-regtest'));
    expect($rows[0]->status())->toBe(IncomingTxStatus::Confirming);
    expect($rows[0]->confirmations())->toBe(1);

    // Mine two more blocks → confirmations = 3 → confirmed.
    $fake->pushBlock(txInBlock(2, 'addr-not-watched'));
    $fake->pushBlock(txInBlock(3, 'addr-not-watched'));
    app(ScanNextBlockAction::class)->handle(new ScanNextBlockData('bitcoin-regtest'));

    $result2 = app(UpdateConfirmationsAction::class)
        ->handle(new UpdateConfirmationsData('bitcoin-regtest'));
    expect($result2->rowsConfirmed)->toBe(1);

    $rows = $incoming->findByChain(new ChainId('bitcoin-regtest'));
    expect($rows[0]->status())->toBe(IncomingTxStatus::Confirmed);
    expect($rows[0]->confirmations())->toBe(3);
});

it('is a no-op when there are no pending rows', function (): void {
    $fake = new FakeBlockSourceFactory();
    $this->app->instance(BlockSourceFactory::class, $fake);

    setupChainAndCursor($fake);

    $result = app(UpdateConfirmationsAction::class)
        ->handle(new UpdateConfirmationsData('bitcoin-regtest'));

    expect($result->rowsExamined)->toBe(0);
});
