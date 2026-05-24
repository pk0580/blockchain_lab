<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\BlockIngestion;

use App\Modules\BlockIngestion\Domain\Entity\IncomingTransaction;
use App\Modules\BlockIngestion\Domain\Event\TransactionDetected;
use App\Modules\BlockIngestion\Domain\Exception\InvalidStatusTransitionException;
use App\Modules\BlockIngestion\Domain\ValueObject\Amount;
use App\Modules\BlockIngestion\Domain\ValueObject\Currency;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTransactionId;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTxStatus;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use DateTimeImmutable;

function makeDetected(int $confirmations = 1): IncomingTransaction
{
    return IncomingTransaction::detected(
        id: new IncomingTransactionId('11111111-2222-3333-4444-555555555555'),
        chainId: new ChainId('bitcoin-regtest'),
        txHash: new TxHash(str_repeat('a', 64)),
        blockHeight: new BlockHeight(102),
        blockHash: new BlockHash(str_repeat('b', 64)),
        fromAddress: null,
        toAddress: 'bcrt1qexampleaddress0000000000000000000000',
        amount: new Amount('50000000'),
        currency: new Currency('BTC'),
        detectedAt: new DateTimeImmutable(),
    );
}

it('emits TransactionDetected on detection', function (): void {
    $tx = makeDetected();
    $events = $tx->pullPendingEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(TransactionDetected::class);
});

it('walks the status machine forward', function (): void {
    $tx = makeDetected();
    $tx->applyConfirmation(3, IncomingTxStatus::Confirming);
    expect($tx->status())->toBe(IncomingTxStatus::Confirming);
    expect($tx->confirmations())->toBe(3);

    $tx->applyConfirmation(6, IncomingTxStatus::Confirmed);
    expect($tx->status())->toBe(IncomingTxStatus::Confirmed);
});

it('refuses backwards confirmation count', function (): void {
    $tx = makeDetected();
    $tx->applyConfirmation(5, IncomingTxStatus::Confirming);
    $tx->applyConfirmation(2, IncomingTxStatus::Confirming);
})->throws(\InvalidArgumentException::class);

it('refuses illegal status regression', function (): void {
    $tx = makeDetected();
    $tx->applyConfirmation(8, IncomingTxStatus::Confirmed);
    $tx->applyConfirmation(9, IncomingTxStatus::Confirming);
})->throws(InvalidStatusTransitionException::class);
