<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Network;

use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;

it('accepts http url with http kind', function (): void {
    $e = new RpcEndpoint('https://rpc.sepolia.org', RpcKind::Http);
    expect($e->url)->toBe('https://rpc.sepolia.org');
});

it('rejects scheme mismatch', function (): void {
    new RpcEndpoint('https://rpc.example.com', RpcKind::WebSocket);
})->throws(\InvalidArgumentException::class);

it('rejects malformed url', function (): void {
    new RpcEndpoint('not-a-url', RpcKind::Http);
})->throws(\InvalidArgumentException::class);
