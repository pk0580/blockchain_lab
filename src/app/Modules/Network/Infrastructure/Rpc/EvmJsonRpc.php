<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Rpc;

use App\Modules\Network\Domain\Exception\BroadcastFailedException;
use App\Modules\Network\Domain\ValueObject\ChainId;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Общий клиент JSON-RPC 2.0 для EVM-узлов. Принадлежит Network::Infrastructure,
 * так как и ChainAdapter, и Withdrawal TxBuilder нуждаются в тонком слое RPC,
 * и мы хотим иметь единое место для настройки таймаутов и политик повторных попыток. URL-адрес для каждой сети
 * передается вызывающей стороной — один экземпляр обслуживает Sepolia, Amoy, Holesky и другие...
 */
final readonly class EvmJsonRpc
{
    public function __construct(
        private HttpFactory $http,
        private int $timeoutSeconds = 5,
        private int $connectTimeoutSeconds = 2,
        private int $retries = 1,
        private int $retryBackoffMs = 150,
    ) {}

    public function blockNumber(string $url): int
    {
        $result = $this->call($url, 'eth_blockNumber', []);
        if (! is_string($result)) {
            throw new RuntimeException('Метод eth_blockNumber вернул результат, отличный от строки.');
        }
        return $this->hexToInt($result);
    }

    public function chainId(string $url): int
    {
        $result = $this->call($url, 'eth_chainId', []);
        if (! is_string($result)) {
            throw new RuntimeException('Метод eth_chainId вернул результат, отличный от строки.');
        }
        return $this->hexToInt($result);
    }

    /**
     * Возвращает txHash при успешной отправке. Любая ошибка узла → исключение
     * с заполненным rpcCode. Транспортный сбой превращается в .transport.
     */
    public function sendRawTransaction(ChainId $chainId, string $url, string $hex): string
    {
        $prefixed = str_starts_with($hex, '0x') ? $hex : '0x'.$hex;

        try {
            $result = $this->call($url, 'eth_sendRawTransaction', [$prefixed]);
        } catch (RpcError $e) {
            throw BroadcastFailedException::rpc($chainId, $e->rpcCode, $e->getMessage());
        } catch (TransportError $e) {
            throw BroadcastFailedException::transport($chainId, $e->getMessage(), $e->getPrevious());
        }

        if (! is_string($result) || $result === '') {
            throw BroadcastFailedException::unexpected($chainId, 'Метод eth_sendRawTransaction вернул пустой результат');
        }
        return $result;
    }

    /**
     * @param list<mixed> $params
     * @return mixed
     */
    public function call(string $url, string $method, array $params): mixed
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
            throw new TransportError("EVM RPC {$method}: {$e->getMessage()}", 0, $e);
        } catch (Throwable $e) {
            throw new TransportError("EVM RPC {$method}: {$e->getMessage()}", 0, $e);
        }

        return $this->decode($response, $method);
    }

    /**
     * @return mixed
     */
    private function decode(Response $response, string $method): mixed
    {
        /** @var array<string, mixed>|null $body */
        $body = $response->json();
        if ($body === null) {
            throw new TransportError("EVM RPC {$method}: status {$response->status()} with non-JSON body");
        }
        if (isset($body['error'])) {
            /** @var array{code?: int|string, message?: string} $err */
            $err = is_array($body['error']) ? $body['error'] : ['message' => (string) $body['error']];
            $code = (string) ($err['code'] ?? 'unknown');
            $msg = (string) ($err['message'] ?? 'unknown');
            throw new RpcError($msg, $code);
        }
        if (! array_key_exists('result', $body)) {
            throw new RuntimeException("В ответе EVM RPC {$method} отсутствует 'result'.");
        }
        return $body['result'];
    }

    private function hexToInt(string $hex): int
    {
        $clean = strtolower(trim($hex));
        if ($clean === '' || $clean === '0x' || $clean === '0x0') {
            return 0;
        }
        if (str_starts_with($clean, '0x')) {
            $clean = substr($clean, 2);
        }
        if (preg_match('/^[0-9a-f]+$/', $clean) !== 1) {
            throw new RuntimeException("EVM RPC: некорректное шестнадцатеричное число '{$hex}'.");
        }
        $value = hexdec($clean);
        return is_int($value) ? $value : (int) $value;
    }
}
