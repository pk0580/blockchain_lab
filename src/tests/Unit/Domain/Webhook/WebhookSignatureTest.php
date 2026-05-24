<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Webhook;

use App\Modules\Webhook\Domain\ValueObject\WebhookSecret;
use App\Modules\Webhook\Domain\ValueObject\WebhookSignature;
use InvalidArgumentException;

it('computes deterministic HMAC-SHA256 signature', function (): void {
    $secret = new WebhookSecret(str_repeat('a', 64));
    $body = '{"event":"x"}';

    $a = WebhookSignature::compute($secret, 1700000000, $body);
    $b = WebhookSignature::compute($secret, 1700000000, $body);

    expect($a->value)->toBe($b->value);
    expect($a->value)->toStartWith('sha256=');
    expect(strlen($a->value))->toBe(7 + 64);
});

it('verifies a correct signature', function (): void {
    $secret = new WebhookSecret(str_repeat('b', 64));
    $sig = WebhookSignature::compute($secret, 1700000100, 'body');
    expect($sig->verify($secret, 1700000100, 'body'))->toBeTrue();
});

it('rejects a forged signature', function (): void {
    $secret = new WebhookSecret(str_repeat('c', 64));
    $sig = WebhookSignature::compute($secret, 1700000200, 'body');
    expect($sig->verify($secret, 1700000200, 'tampered'))->toBeFalse();
});

it('rejects signature with wrong format', function (): void {
    expect(fn () => new WebhookSignature('not-a-sig'))
        ->toThrow(InvalidArgumentException::class);
});

it('different timestamps yield different signatures', function (): void {
    $secret = new WebhookSecret(str_repeat('d', 64));
    $a = WebhookSignature::compute($secret, 1, 'body')->value;
    $b = WebhookSignature::compute($secret, 2, 'body')->value;
    expect($a)->not->toBe($b);
});
