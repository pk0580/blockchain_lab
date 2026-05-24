<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Network;

use App\Modules\Network\Domain\Exception\UnknownChainFamilyException;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

it('parses bitcoin / evm / tron case-insensitively', function (): void {
    expect(ChainFamily::fromString('bitcoin'))->toBe(ChainFamily::Bitcoin);
    expect(ChainFamily::fromString('EVM'))->toBe(ChainFamily::Evm);
    expect(ChainFamily::fromString('Tron'))->toBe(ChainFamily::Tron);
});

it('throws on unknown family', function (): void {
    ChainFamily::fromString('solana');
})->throws(UnknownChainFamilyException::class);
