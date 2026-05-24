<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Webhook;

use App\Modules\Webhook\Domain\ValueObject\WebhookSecret;
use InvalidArgumentException;

it('accepts a 32-char secret', function (): void {
    $s = new WebhookSecret(str_repeat('x', 32));
    expect($s->value)->toHaveLength(32);
});

it('rejects too-short secret', function (): void {
    expect(fn () => new WebhookSecret(str_repeat('a', 16)))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects too-long secret', function (): void {
    expect(fn () => new WebhookSecret(str_repeat('a', 129)))
        ->toThrow(InvalidArgumentException::class);
});

it('exposes a masked representation but never raw value via toString', function (): void {
    $s = new WebhookSecret(str_repeat('a', 60).'lastFour');
    expect($s->masked())->toBe('****Four');
});
