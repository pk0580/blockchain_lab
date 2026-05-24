<?php

declare(strict_types=1);

namespace App\Modules\Fee\Infrastructure\Rpc;

use App\Modules\Fee\Domain\Exception\FeeEstimationFailedException;
use App\Modules\Network\Domain\ValueObject\ChainId;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * JSON-RPC 2.0 клиент под минимальный EVM-набор для Fee. URL передаётся
 * per call; одна инстанция обслуживает любое количество EVM-сетей (Sepolia,
 * Amoy, ...).
 *
 * Hex-числа парсим строго в строку wei через bcmath. baseFee на мейннете в
 * шторм превышает PHP_INT_MAX, поэтому int нам не подходит.
 */
final readonly class EvmRpcClient
{
    public function __construct(
        private HttpFactory $http,
        private int $timeoutSeconds = 5,
        private int $connectTimeoutSeconds = 2,
        private int $retries = 1,
        private int $retryBackoffMs = 150,
    ) {}

    /**
     * @param list<int> $rewardPercentiles
     * @return array{base_fees_wei: list<numeric-string>, rewards_wei: list<list<numeric-string>>}
     */
    public function feeHistory(ChainId $chainId, string $url, int $blockCount, string $newestBlock, array $rewardPercentiles): array
    {
        $raw = $this->call($chainId, $url, 'eth_feeHistory', [
            // EVM JSON-RPC требует hex-кодированный quantity, кроме percentiles.
            '0x'.dechex($blockCount),
            $newestBlock,
            $rewardPercentiles,
        ]);

        if (! is_array($raw)) {
            throw FeeEstimationFailedException::unusableResponse(
                $chainId,
                'eth_feeHistory result is not an object'
            );
        }

        $baseFees = [];
        $rawBaseFees = $raw['baseFeePerGas'] ?? null;
        if (is_array($rawBaseFees)) {
            foreach ($rawBaseFees as $hex) {
                $baseFees[] = $this->hexToDecString((string) $hex);
            }
        }

        $rewards = [];
        $rawRewards = $raw['reward'] ?? null;
        if (is_array($rawRewards)) {
            foreach ($rawRewards as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rewardRow = [];
                foreach ($row as $hex) {
                    $rewardRow[] = $this->hexToDecString((string) $hex);
                }
                $rewards[] = $rewardRow;
            }
        }

        return [
            'base_fees_wei' => $baseFees,
            'rewards_wei' => $rewards,
        ];
    }

    /**
     * @param list<mixed> $params
     */
    private function call(ChainId $chainId, string $url, string $method, array $params): mixed
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ];

        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->connectTimeout($this->connectTimeoutSeconds)
                ->retry($this->retries, $this->retryBackoffMs, throw: false)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw FeeEstimationFailedException::rpc($chainId, $e->getMessage(), $e);
        } catch (Throwable $e) {
            throw FeeEstimationFailedException::rpc($chainId, $e->getMessage(), $e);
        }

        return $this->decode($response, $chainId, $method);
    }

    private function decode(Response $response, ChainId $chainId, string $method): mixed
    {
        /** @var array<string, mixed>|null $body */
        $body = $response->json();
        if ($body === null) {
            throw FeeEstimationFailedException::unusableResponse(
                $chainId,
                "{$method}: non-JSON body, status {$response->status()}"
            );
        }
        if (isset($body['error'])) {
            $err = is_array($body['error']) ? $body['error'] : ['message' => (string) $body['error']];
            $msg = (string) ($err['message'] ?? 'unknown');
            throw FeeEstimationFailedException::rpc($chainId, "{$method} error: {$msg}");
        }
        if (! array_key_exists('result', $body)) {
            throw FeeEstimationFailedException::unusableResponse(
                $chainId,
                "{$method} response missing 'result'"
            );
        }
        return $body['result'];
    }

    /**
     * "0x1a" → "26". Поддерживает произвольно большие значения через bcmath.
     *
     * @return numeric-string
     */
    private function hexToDecString(string $hex): string
    {
        $clean = strtolower(ltrim($hex));
        if ($clean === '' || $clean === '0x' || $clean === '0x0') {
            return '0';
        }
        if (str_starts_with($clean, '0x')) {
            $clean = substr($clean, 2);
        }
        if (! preg_match('/^[0-9a-f]+$/', $clean)) {
            return '0';
        }

        $dec = '0';
        $len = strlen($clean);
        for ($i = 0; $i < $len; $i++) {
            $digit = (string) hexdec($clean[$i]);
            $dec = bcadd(bcmul($dec, '16', 0), $digit, 0);
        }
        return $dec;
    }
}
