<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Webhook;

use App\Modules\Webhook\Domain\ValueObject\WebhookUrl;
use InvalidArgumentException;

it('accepts https URLs by default', function (): void {
    expect((new WebhookUrl('https://example.com/hook'))->value)->toBe('https://example.com/hook');
});

it('rejects http URLs by default', function (): void {
    expect(fn () => new WebhookUrl('http://example.com/hook'))
        ->toThrow(InvalidArgumentException::class);
});

it('accepts http URLs when explicitly allowed', function (): void {
    expect((new WebhookUrl('http://example.com/hook', allowInsecure: true))->value)
        ->toBe('http://example.com/hook');
});

it('rejects malformed URLs', function (): void {
    expect(fn () => new WebhookUrl('not-a-url'))
        ->toThrow(InvalidArgumentException::class);
});
