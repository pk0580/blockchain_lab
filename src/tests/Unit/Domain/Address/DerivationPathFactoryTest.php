<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Address;

use App\Modules\Address\Domain\Service\DerivationPathFactory;
use App\Modules\Address\Domain\ValueObject\DerivationIndex;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

it('emits BIP-84 P2WPKH paths for Bitcoin', function (): void {
    $factory = new DerivationPathFactory();
    expect((string) $factory->buildExternal(ChainFamily::Bitcoin, new DerivationIndex(0)))
        ->toBe("m/84'/0'/0'/0/0");
});

it('emits BIP-44 ETH paths for the EVM family', function (): void {
    $factory = new DerivationPathFactory();
    expect((string) $factory->buildExternal(ChainFamily::Evm, new DerivationIndex(7)))
        ->toBe("m/44'/60'/0'/0/7");
});

it('emits BIP-44 Tron paths with SLIP-44 coin type 195', function (): void {
    $factory = new DerivationPathFactory();
    expect((string) $factory->buildExternal(ChainFamily::Tron, new DerivationIndex(0)))
        ->toBe("m/44'/195'/0'/0/0");
});
