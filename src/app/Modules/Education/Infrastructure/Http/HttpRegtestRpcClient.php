<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Http;

use App\Modules\Education\Application\Contract\RegtestRpcClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

final readonly class HttpRegtestRpcClient implements RegtestRpcClient
{
    public function __construct(
        private HttpFactory $http,
        private string $url,
        private string $user,
        private string $password,
        private int $timeoutSeconds = 5,
        private int $connectTimeoutSeconds = 2,
    ) {}

    public function getBlockCount(): int
    {
        return (int) $this->call('getblockcount', []);
    }

    public function getBestBlockHash(): string
    {
        $hash = $this->call('getbestblockhash', []);
        if (! is_string($hash) || $hash === '') {
            throw new RuntimeException('getbestblockhash returned empty result');
        }
        return $hash;
    }

    public function getBlock(string $hash, int $verbosity = 1): array
    {
        $block = $this->call('getblock', [$hash, $verbosity]);
        if (! is_array($block)) {
            throw new RuntimeException('getblock did not return an array');
        }
        /** @var array<string, mixed> $block */
        return $block;
    }

    public function getRawMempool(): array
    {
        $result = $this->call('getrawmempool', [false]);
        if (! is_array($result)) {
            return [];
        }
        /** @var list<string> $strings */
        $strings = [];
        foreach ($result as $entry) {
            if (is_string($entry)) {
                $strings[] = $entry;
            }
        }
        return $strings;
    }

    public function generateToAddress(int $blocks, string $address): array
    {
        $result = $this->call('generatetoaddress', [$blocks, $address]);
        if (! is_array($result)) {
            return [];
        }
        /** @var list<string> $hashes */
        $hashes = [];
        foreach ($result as $h) {
            if (is_string($h)) {
                $hashes[] = $h;
            }
        }
        return $hashes;
    }

    public function invalidateBlock(string $hash): void
    {
        // bitcoind возвращает null на успех. Если RPC бросит ошибку — call()
        // конвертирует в RuntimeException.
        $this->call('invalidateblock', [$hash]);
    }

    public function getNewAddress(): ?string
    {
        try {
            $result = $this->call('getnewaddress', []);
        } catch (RuntimeException) {
            // Кошелёк может быть не подключён (`-disablewallet`) — это OK.
            return null;
        }
        return is_string($result) ? $result : null;
    }

    /**
     * @param  list<mixed>  $params
     * @return mixed
     */
    private function call(string $method, array $params): mixed
    {
        $payload = [
            'jsonrpc' => '1.0',
            'id' => $method,
            'method' => $method,
            'params' => $params,
        ];

        try {
            $response = $this->http
                ->withBasicAuth($this->user, $this->password)
                ->timeout($this->timeoutSeconds)
                ->connectTimeout($this->connectTimeoutSeconds)
                ->acceptJson()
                ->asJson()
                ->post($this->url, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException("regtest_rpc_unreachable: {$e->getMessage()}", 0, $e);
        } catch (Throwable $e) {
            throw new RuntimeException("regtest_rpc_failed: {$e->getMessage()}", 0, $e);
        }

        return $this->decode($response, $method);
    }

    private function decode(Response $response, string $method): mixed
    {
        /** @var array<string, mixed>|null $body */
        $body = $response->json();
        if ($body === null) {
            throw new RuntimeException("regtest_rpc {$method}: non-JSON body, status {$response->status()}");
        }
        if (isset($body['error'])) {
            $err = is_array($body['error']) ? $body['error'] : ['message' => (string) $body['error']];
            $message = isset($err['message']) ? (string) $err['message'] : 'unknown';
            $code = isset($err['code']) ? (string) $err['code'] : 'unknown';
            throw new RuntimeException("regtest_rpc {$method} failed [{$code}]: {$message}");
        }
        if (! array_key_exists('result', $body)) {
            throw new RuntimeException("regtest_rpc {$method}: 'result' missing in response");
        }
        return $body['result'];
    }
}
