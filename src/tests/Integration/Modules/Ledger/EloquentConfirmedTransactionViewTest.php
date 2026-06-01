<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Ledger;

use App\Modules\Ledger\Infrastructure\Adapter\EloquentConfirmedTransactionView;
use App\Modules\Ledger\Infrastructure\Persistence\Eloquent\Models\IncomingTransactionLookupModel;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->chainRepository = app(ChainRepository::class);
    $this->view = new EloquentConfirmedTransactionView($this->chainRepository);

    $chain = Chain::reconstitute(
        id: new ChainId('bitcoin-mainnet'),
        name: new ChainName('Bitcoin'),
        family: ChainFamily::Bitcoin,
        nativeCurrency: new NativeCurrency('BTC', 8),
        confirmationRequirement: new ConfirmationRequirement(2, 6),
        endpoints: [new RpcEndpoint('http://localhost:8332', RpcKind::Http)],
        enabled: true,
        registeredAt: new DateTimeImmutable(),
    );
    $this->chainRepository->save($chain);
});

function insertIncomingTransaction(string $id, string $amount): void
{
    IncomingTransactionLookupModel::query()->insert([
        'id' => $id,
        'chain_id' => 'bitcoin-mainnet',
        'tx_hash' => str_repeat('a', 64),
        'block_height' => 100,
        'to_address' => 'address-1',
        'amount' => $amount,
        'currency' => 'BTC',
        'status' => 'confirmed',
        'detected_at' => now(),
    ]);
}

it('maps a confirmed transaction to an integer minor-unit amount', function () {
    insertIncomingTransaction('11111111-1111-1111-1111-111111111111', '500000000');

    $data = $this->view->findById('11111111-1111-1111-1111-111111111111');

    expect($data)->not->toBeNull();
    expect($data->money->amount)->toBe('500000000');
    expect($data->money->currency)->toBe('BTC');
    expect($data->blockHeight)->toBe(100);
});

it('keeps a large integer amount exact (no float rounding)', function () {
    // Значение, которое старый float-путь округлил бы; здесь должно дойти как есть.
    $amount = '1000000000000000001';
    insertIncomingTransaction('22222222-2222-2222-2222-222222222222', $amount);

    $data = $this->view->findById('22222222-2222-2222-2222-222222222222');

    expect($data->money->amount)->toBe($amount);
});

it('returns null when the chain is unknown', function () {
    IncomingTransactionLookupModel::query()->insert([
        'id' => '33333333-3333-3333-3333-333333333333',
        'chain_id' => 'unknown-chain',
        'tx_hash' => str_repeat('c', 64),
        'block_height' => 100,
        'to_address' => 'address-3',
        'amount' => '500000000',
        'currency' => 'BTC',
        'status' => 'confirmed',
        'detected_at' => now(),
    ]);

    expect($this->view->findById('33333333-3333-3333-3333-333333333333'))->toBeNull();
});

it('returns null for an unconfirmed transaction', function () {
    IncomingTransactionLookupModel::query()->insert([
        'id' => '44444444-4444-4444-4444-444444444444',
        'chain_id' => 'bitcoin-mainnet',
        'tx_hash' => str_repeat('b', 64),
        'block_height' => null,
        'to_address' => 'address-2',
        'amount' => '500000000',
        'currency' => 'BTC',
        'status' => 'pending',
        'detected_at' => now(),
    ]);

    expect($this->view->findById('44444444-4444-4444-4444-444444444444'))->toBeNull();
});
