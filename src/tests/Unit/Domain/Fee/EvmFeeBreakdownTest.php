<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fee;

use App\Modules\Fee\Domain\ValueObject\EvmFeeBreakdown;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

it('exposes the evm family', function (): void {
    $b = new EvmFeeBreakdown(
        maxFeePerGasWei: '20000000000',
        maxPriorityFeePerGasWei: '2000000000',
        gasLimit: 21000,
    );
    expect($b->family())->toBe(ChainFamily::Evm);
});

it('serializes to canonical array', function (): void {
    $b = new EvmFeeBreakdown(
        maxFeePerGasWei: '50000000000',
        maxPriorityFeePerGasWei: '1500000000',
        gasLimit: 21000,
    );
    expect($b->toArray())->toBe([
        'family' => 'evm',
        'max_fee_per_gas_wei' => '50000000000',
        'max_priority_fee_per_gas_wei' => '1500000000',
        'gas_limit' => 21000,
    ]);
});

it('accepts huge wei values (above PHP int64)', function (): void {
    $huge = str_repeat('9', 30);
    $b = new EvmFeeBreakdown(
        maxFeePerGasWei: $huge,
        maxPriorityFeePerGasWei: '0',
        gasLimit: 21000,
    );
    expect($b->maxFeePerGasWei)->toBe($huge);
});

it('rejects priority > maxFee (EIP-1559 invariant)', function (): void {
    new EvmFeeBreakdown(
        maxFeePerGasWei: '1000000000',
        maxPriorityFeePerGasWei: '2000000000',
        gasLimit: 21000,
    );
})->throws(\InvalidArgumentException::class);

it('rejects sub-21000 gasLimit (native transfer floor)', function (): void {
    new EvmFeeBreakdown(
        maxFeePerGasWei: '1',
        maxPriorityFeePerGasWei: '1',
        gasLimit: 20999,
    );
})->throws(\InvalidArgumentException::class);

it('rejects malformed wei strings', function (): void {
    new EvmFeeBreakdown(
        maxFeePerGasWei: '1.5',
        maxPriorityFeePerGasWei: '0',
        gasLimit: 21000,
    );
})->throws(\InvalidArgumentException::class);
