<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\BlockSource;

use App\Modules\BlockIngestion\Domain\Contract\BlockSource;
use App\Modules\BlockIngestion\Domain\Contract\BlockSourceFactory;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * Resolves the right block source per chain family. Phase 4 only ships the
 * Bitcoin implementation — other families throw, which is louder than silently
 * scanning nothing.
 */
final readonly class BitcoinCoreBlockSourceFactory implements BlockSourceFactory
{
    public function __construct(
        private HttpFactory $http,
        private string $defaultUser,
        private string $defaultPassword,
        private int $timeoutSeconds = 5,
        private int $connectTimeoutSeconds = 2,
        private int $retries = 1,
        private int $retryBackoffMs = 150,
    ) {}

    public function for(Chain $chain): BlockSource
    {
        if ($chain->family !== ChainFamily::Bitcoin) {
            throw new RuntimeException(
                "BitcoinCoreBlockSourceFactory cannot build a source for family "
                ."'{$chain->family->value}'; Phase 4 only supports bitcoin."
            );
        }

        $endpoint = null;
        foreach ($chain->endpoints() as $candidate) {
            if ($candidate->kind === RpcKind::Http) {
                $endpoint = $candidate;
                break;
            }
        }
        if ($endpoint === null) {
            throw new RuntimeException(
                "Chain '{$chain->id->value}' has no http RPC endpoint configured."
            );
        }

        $client = new BitcoinRpcClient(
            http: $this->http,
            url: $endpoint->url,
            user: $this->defaultUser,
            password: $this->defaultPassword,
            timeoutSeconds: $this->timeoutSeconds,
            connectTimeoutSeconds: $this->connectTimeoutSeconds,
            retries: $this->retries,
            retryBackoffMs: $this->retryBackoffMs,
        );

        return new BitcoinCoreBlockSource($client, $chain);
    }
}
