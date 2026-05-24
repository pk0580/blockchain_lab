<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fee;

use App\Modules\Fee\Domain\ValueObject\BitcoinFeeBreakdown;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

it('exposes the bitcoin family', function (): void {
    $b = new BitcoinFeeBreakdown(satPerVbyte: 5);
    expect($b->family())->toBe(ChainFamily::Bitcoin);
});

it('serializes to canonical array', function (): void {
    $b = new BitcoinFeeBreakdown(satPerVbyte: 42);
    expect($b->toArray())->toBe([
        'family' => 'bitcoin',
        'sat_per_vbyte' => 42,
    ]);
});

it('rejects satPerVbyte below mempool min-relay (1)', function (): void {
    new BitcoinFeeBreakdown(satPerVbyte: 0);
})->throws(\InvalidArgumentException::class);
