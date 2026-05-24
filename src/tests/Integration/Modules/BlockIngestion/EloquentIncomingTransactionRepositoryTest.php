<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\BlockIngestion;

use App\Modules\BlockIngestion\Domain\Entity\IncomingTransaction;
use App\Modules\BlockIngestion\Domain\Repository\IncomingTransactionRepository;
use App\Modules\BlockIngestion\Domain\ValueObject\Amount;
use App\Modules\BlockIngestion\Domain\ValueObject\Currency;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTransactionId;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\TxHash;
use DateTimeImmutable;
use Illuminate\Support\Str;

function makeIncomingTransaction(string $to = 'bcrt1qaddrtest00000000000000000000000000abc'): IncomingTransaction
{
    return IncomingTransaction::detected(
        id: new IncomingTransactionId((string) Str::uuid()),
        chainId: new ChainId('bitcoin-regtest'),
        txHash: new TxHash(str_repeat('a', 64)),
        blockHeight: new BlockHeight(102),
        blockHash: new BlockHash(str_repeat('b', 64)),
        fromAddress: null,
        toAddress: $to,
        amount: new Amount('50000000'),
        currency: new Currency('BTC'),
        detectedAt: new DateTimeImmutable(),
    );
}

it('round-trips an IncomingTransaction and preserves precision', function (): void {
    /** @var IncomingTransactionRepository $repo */
    $repo = app(IncomingTransactionRepository::class);

    $repo->save(makeIncomingTransaction());

    $list = $repo->findByChain(new ChainId('bitcoin-regtest'));
    expect($list)->toHaveCount(1);
    expect($list[0]->amount->value)->toBe('50000000');
    expect($list[0]->currency->code)->toBe('BTC');
});

it('detects duplicates by (chain, tx, recipient)', function (): void {
    /** @var IncomingTransactionRepository $repo */
    $repo = app(IncomingTransactionRepository::class);

    $repo->save(makeIncomingTransaction());

    expect($repo->existsForRecipient(
        new ChainId('bitcoin-regtest'),
        new TxHash(str_repeat('a', 64)),
        'bcrt1qaddrtest00000000000000000000000000abc',
    ))->toBeTrue();

    expect($repo->existsForRecipient(
        new ChainId('bitcoin-regtest'),
        new TxHash(str_repeat('a', 64)),
        'someotheraddress',
    ))->toBeFalse();
});
