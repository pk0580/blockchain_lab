<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Network;

use App\Modules\Network\Domain\ValueObject\ChainId;

it('accepts kebab-case identifiers', function (): void {
    expect((new ChainId('ethereum-sepolia'))->value)->toBe('ethereum-sepolia');
});

it('rejects uppercase', function (): void {
    new ChainId('Ethereum');
})->throws(\InvalidArgumentException::class);

it('rejects too short', function (): void {
    new ChainId('a');
})->throws(\InvalidArgumentException::class);

it('rejects leading hyphen', function (): void {
    new ChainId('-eth');
})->throws(\InvalidArgumentException::class);

it('compares by value', function (): void {
    expect((new ChainId('btc'))->equals(new ChainId('btc')))->toBeTrue();
    expect((new ChainId('btc'))->equals(new ChainId('eth')))->toBeFalse();
});
