<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Idempotency;

use App\Modules\Idempotency\Domain\ValueObject\IdempotencyKey;
use InvalidArgumentException;

it('accepts valid identifiers in allowed charset', function (string $value): void {
    $key = new IdempotencyKey($value);
    expect($key->value)->toBe($value);
})->with([
    'plain uuid' => '11111111-1111-4111-8111-111111111111',
    'ulid-style' => '01HN1ABCDEFGHIJK0123456789',
    'colon-namespaced' => 'order:create:abc-123',
    'dotted' => 'tenant.42.req_001',
]);

it('rejects out-of-range length', function (string $value): void {
    expect(fn () => new IdempotencyKey($value))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'too short (7)' => 'abc1234',
    'too long (121)' => str_repeat('x', 121),
    'empty' => '',
]);

it('rejects forbidden characters', function (string $value): void {
    expect(fn () => new IdempotencyKey($value))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'whitespace inside' => 'key with space',
    'slash' => 'tenant/42/key',
    'plus' => 'a+b+c+d+e+f',
    'unicode' => 'клю-ABCDEF',
]);
