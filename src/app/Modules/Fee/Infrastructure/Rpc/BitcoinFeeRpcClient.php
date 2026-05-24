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
 * JSON-RPC 1.0 клиент только под `estimatesmartfee`. URL берётся из
 * `Chain::endpoints()` per вызов — это позволяет одной инстанции работать
 * сразу с несколькими BTC chain'ами (regtest + testnet) без отдельного
 * factory.
 */
final readonly class BitcoinFeeRpcClient
{
    public function __construct(
        private HttpFactory $http,
        private string $user,
        private string $password,
        private int $timeoutSeconds = 5,
        private int $connectTimeoutSeconds = 2,
        private int $retries = 1,
        private int $retryBackoffMs = 150,
    ) {}

    /**
     * @return array{feerate?: float|string|int, blocks?: int, errors?: list<string>}
     */
    public function estimateSmartFee(ChainId $chainId, string $url, int $confirmTarget, string $mode): array
    {
        $payload = [
            'jsonrpc' => '1.0',
            'id' => 'estimatesmartfee',
            'method' => 'estimatesmartfee',
            'params' => [$confirmTarget, $mode],
        ];

        try {
            $response = $this->http
                ->withBasicAuth($this->user, $this->password)
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

        return $this->decode($response, $chainId);
    }

    /**
     * @return array{feerate?: float|string|int, blocks?: int, errors?: list<string>}
     */
    private function decode(Response $response, ChainId $chainId): array
    {
        /** @var array<string, mixed>|null $body */
        $body = $response->json();
        if ($body === null) {
            throw FeeEstimationFailedException::unusableResponse(
                $chainId,
                "non-JSON body, status {$response->status()}"
            );
        }
        if (isset($body['error'])) {
            $err = is_array($body['error']) ? $body['error'] : ['message' => (string) $body['error']];
            $msg = (string) ($err['message'] ?? 'unknown');
            throw FeeEstimationFailedException::rpc($chainId, "estimatesmartfee error: {$msg}");
        }
        $result = $body['result'] ?? null;
        if (! is_array($result)) {
            throw FeeEstimationFailedException::unusableResponse(
                $chainId,
                'estimatesmartfee returned non-object result'
            );
        }
        /** @var array{feerate?: float|string|int, blocks?: int, errors?: list<string>} $result */
        return $result;
    }
}
