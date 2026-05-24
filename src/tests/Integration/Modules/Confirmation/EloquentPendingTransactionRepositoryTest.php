<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Confirmation;

use App\Modules\BlockIngestion\Domain\Entity\IncomingTransaction;
use App\Modules\BlockIngestion\Domain\Repository\IncomingTransactionRepository;
use App\Modules\BlockIngestion\Domain\ValueObject\Amount;
use App\Modules\BlockIngestion\Domain\ValueObject\Currency;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTransactionId;
use App\Modules\Confirmation\Domain\Repository\PendingTransactionRepository;
use App\Modules\Confirmation\Domain\ValueObject\ConfirmationOutcome;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use DateTimeImmutable;
use Illuminate\Support\Str;

it('lists only non-terminal rows for a chain', function (): void {
    /** @var IncomingTransactionRepository $writes */
    $writes = app(IncomingTransactionRepository::class);
    /** @var PendingTransactionRepository $reads */
    $reads = app(PendingTransactionRepository::class);

    $writes->save(IncomingTransaction::detected(
        id: new IncomingTransactionId((string) Str::uuid()),
        chainId: new ChainId('bitcoin-regtest'),
        txHash: new TxHash(str_repeat('a', 64)),
        blockHeight: new BlockHeight(102),
        blockHash: new BlockHash(str_repeat('b', 64)),
        fromAddress: null,
        toAddress: 'addr-detected',
        amount: new Amount('1'),
        currency: new Currency('BTC'),
        detectedAt: new DateTimeImmutable(),
    ));

    $pending = $reads->findPending(new ChainId('bitcoin-regtest'));
    expect($pending)->toHaveCount(1);
    expect($pending[0]->status)->toBe(ConfirmationOutcome::Detected);
});

it('applies an outcome update', function (): void {
    /** @var IncomingTransactionRepository $writes */
    $writes = app(IncomingTransactionRepository::class);
    /** @var PendingTransactionRepository $reads */
    $reads = app(PendingTransactionRepository::class);

    $id = (string) Str::uuid();
    $writes->save(IncomingTransaction::detected(
        id: new IncomingTransactionId($id),
        chainId: new ChainId('bitcoin-regtest'),
        txHash: new TxHash(str_repeat('c', 64)),
        blockHeight: new BlockHeight(102),
        blockHash: new BlockHash(str_repeat('d', 64)),
        fromAddress: null,
        toAddress: 'addr-confirming',
        amount: new Amount('1'),
        currency: new Currency('BTC'),
        detectedAt: new DateTimeImmutable(),
    ));

    $reads->applyOutcome($id, 3, ConfirmationOutcome::Confirming);

    $pending = $reads->findPending(new ChainId('bitcoin-regtest'));
    expect($pending[0]->status)->toBe(ConfirmationOutcome::Confirming);
    expect($pending[0]->confirmations)->toBe(3);
});
