<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Network;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;
use InvalidArgumentException;

it('accepts a bitcoin hex without 0x prefix', function (): void {
    $tx = new SignedRawTx(ChainFamily::Bitcoin, '0100abcd');
    expect($tx->hex)->toBe('0100abcd');
    expect($tx->withoutPrefix())->toBe('0100abcd');
});

it('accepts an evm hex with 0x prefix', function (): void {
    $tx = new SignedRawTx(ChainFamily::Evm, '0x02f8b0');
    expect($tx->hex)->toBe('0x02f8b0');
    expect($tx->withPrefix())->toBe('0x02f8b0');
    expect($tx->withoutPrefix())->toBe('02f8b0');
});

it('rejects bitcoin payload carrying the 0x prefix', function (): void {
    expect(fn () => new SignedRawTx(ChainFamily::Bitcoin, '0x0100abcd'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects evm payload missing the 0x prefix', function (): void {
    expect(fn () => new SignedRawTx(ChainFamily::Evm, '0100abcd'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an empty payload', function (): void {
    expect(fn () => new SignedRawTx(ChainFamily::Bitcoin, ''))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects non-hex bodies for any family', function (): void {
    expect(fn () => new SignedRawTx(ChainFamily::Evm, '0xzzzz'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects odd-length bodies (half-byte)', function (): void {
    expect(fn () => new SignedRawTx(ChainFamily::Bitcoin, 'abc'))
        ->toThrow(InvalidArgumentException::class);
});
