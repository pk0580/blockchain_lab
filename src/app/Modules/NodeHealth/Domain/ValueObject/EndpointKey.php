<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Domain\ValueObject;

use App\Modules\Network\Domain\ValueObject\ChainId;
use InvalidArgumentException;

/**
 * Стабильный ключ endpoint'а: пара (chain_id, url). URL уникально идентифицирует
 * провайдер внутри chain'а (Infura vs Alchemy vs self-hosted).
 *
 * URL хешируется (sha256) при сериализации в cache-ключ, чтобы избегать
 * проблем с длинными URL и спецсимволами.
 */
final readonly class EndpointKey
{
    public function __construct(
        public ChainId $chainId,
        public string $url,
    ) {
        if (trim($url) === '') {
            throw new InvalidArgumentException('EndpointKey URL must be non-empty.');
        }
        if (strlen($url) > 500) {
            throw new InvalidArgumentException("EndpointKey URL too long: {$url}.");
        }
    }

    public function cacheKey(): string
    {
        return sprintf('node_health:%s:%s', $this->chainId->value, hash('sha256', $this->url));
    }

    public function equals(self $other): bool
    {
        return $this->chainId->equals($other->chainId) && $this->url === $other->url;
    }
}
