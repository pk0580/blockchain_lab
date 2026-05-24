<?php

declare(strict_types=1);

namespace Tests\Feature\Address;

use App\Modules\Address\Application\UseCase\ValidateAddress\ValidateAddressAction;
use App\Modules\Address\Application\UseCase\ValidateAddress\ValidateAddressData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Cache::flush();
});

it('returns true for a valid EVM address and caches the result', function (): void {
    Http::fake([
        'http://signing-svc:8080/v1/addresses/validate' => Http::response(['valid' => true], 200),
    ]);

    $action = app(ValidateAddressAction::class);

    $first = $action->handle(new ValidateAddressData('evm', '0x9858EfFD232B4033E47d90003D41EC34EcaEda94'));
    $second = $action->handle(new ValidateAddressData('evm', '0x9858EfFD232B4033E47d90003D41EC34EcaEda94'));

    expect($first)->toBeTrue();
    expect($second)->toBeTrue();
    // Only one HTTP call — the second one is served from cache.
    Http::assertSentCount(1);
});

it('returns false on rejected address and does not cache forever', function (): void {
    Http::fake([
        'http://signing-svc:8080/v1/addresses/validate' => Http::response(['valid' => false], 200),
    ]);

    $action = app(ValidateAddressAction::class);
    expect($action->handle(new ValidateAddressData('evm', '0xnonsense')))->toBeFalse();
});
