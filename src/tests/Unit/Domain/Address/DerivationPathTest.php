<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Address;

use App\Modules\Address\Domain\Exception\InvalidDerivationPathException;
use App\Modules\Address\Domain\ValueObject\DerivationPath;

it('accepts BIP-32 hardened paths', function (): void {
    expect((string) new DerivationPath("m/44'/60'/0'/0/0"))->toBe("m/44'/60'/0'/0/0");
});

it('accepts the h hardening notation too', function (): void {
    expect((string) new DerivationPath('m/44h/60h/0h/0/0'))->toBe('m/44h/60h/0h/0/0');
});

it('extracts the leaf index', function (): void {
    expect((new DerivationPath("m/44'/60'/0'/0/7"))->leafIndex()->value)->toBe(7);
    expect((new DerivationPath("m/84'/0'/0'/0/123"))->leafIndex()->value)->toBe(123);
});

it('rejects paths without the m/ prefix', function (): void {
    new DerivationPath("44'/60'/0'/0/0");
})->throws(InvalidDerivationPathException::class);

it('rejects paths with negative numbers', function (): void {
    new DerivationPath("m/-1/60'/0'/0/0");
})->throws(InvalidDerivationPathException::class);
