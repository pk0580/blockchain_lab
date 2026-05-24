<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\NodeHealth;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointKey;
use InvalidArgumentException;

it('builds stable cache key from chainId + url hash', function (): void {
    $key = new EndpointKey(new ChainId('bitcoin-regtest'), 'http://host:1');
    $cacheKey = $key->cacheKey();

    expect($cacheKey)->toStartWith('node_health:bitcoin-regtest:');
    // Тот же ключ при двух конструкциях.
    $other = new EndpointKey(new ChainId('bitcoin-regtest'), 'http://host:1');
    expect($other->cacheKey())->toBe($cacheKey);
});

it('rejects empty URL', function (): void {
    expect(fn () => new EndpointKey(new ChainId('x-1'), ''))
        ->toThrow(InvalidArgumentException::class);
});

it('treats different URLs as different keys', function (): void {
    $a = new EndpointKey(new ChainId('x-1'), 'http://a');
    $b = new EndpointKey(new ChainId('x-1'), 'http://b');
    expect($a->equals($b))->toBeFalse();
});
