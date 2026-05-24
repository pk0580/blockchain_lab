<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Idempotency;

use App\Modules\Idempotency\Domain\ValueObject\RequestHash;
use InvalidArgumentException;

it('produces 64-char lower-hex sha256 of canonical form', function (): void {
    $hash = RequestHash::ofRequest('post', '/api/v1/orders', '{"a":1}');

    expect($hash->value)->toMatch('/^[a-f0-9]{64}$/');
    expect($hash->value)
        ->toBe(hash('sha256', "POST\n/api/v1/orders\n".'{"a":1}'));
});

it('different bodies produce different hashes', function (): void {
    $a = RequestHash::ofRequest('POST', '/x', '{"a":1}');
    $b = RequestHash::ofRequest('POST', '/x', '{"a":2}');
    expect($a->equals($b))->toBeFalse();
});

it('method is normalized to upper-case for canonical form', function (): void {
    $a = RequestHash::ofRequest('post', '/x', 'b');
    $b = RequestHash::ofRequest('POST', '/x', 'b');
    expect($a->equals($b))->toBeTrue();
});

it('rejects malformed hex', function (string $value): void {
    expect(fn () => new RequestHash($value))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'too short' => str_repeat('a', 63),
    'too long' => str_repeat('a', 65),
    'upper hex' => str_repeat('A', 64),
    'non-hex' => str_repeat('g', 64),
]);
