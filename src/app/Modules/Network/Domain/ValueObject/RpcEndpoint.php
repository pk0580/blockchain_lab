<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\ValueObject;

use InvalidArgumentException;

final readonly class RpcEndpoint
{
    public function __construct(
        public string $url,
        public RpcKind $kind,
        public int $priority = 100,
        public int $weight = 1,
    ) {
        $parsed = parse_url($url);
        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            throw new InvalidArgumentException("RpcEndpoint URL is malformed: '{$url}'.");
        }
        $expected = $kind === RpcKind::Http ? ['http', 'https'] : ['ws', 'wss'];
        if (! in_array($parsed['scheme'], $expected, strict: true)) {
            throw new InvalidArgumentException(
                "RpcEndpoint kind {$kind->value} requires scheme in [".implode(',', $expected).
                "], got '{$parsed['scheme']}'."
            );
        }
        if ($priority < 0 || $weight < 0) {
            throw new InvalidArgumentException('RpcEndpoint priority and weight must be >= 0.');
        }
    }
}
