<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Infrastructure\BlockSource;

use App\Modules\BlockIngestion\Domain\Exception\BlockSourceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Легковесный клиент JSON-RPC 1.0 для Bitcoin Core. Обертывает таймауты и повторные попытки,
 * и пробрасывает ошибки вышестоящего узла как {@see BlockSourceException}, чтобы уровень
 * приложения оставался независимым от драйвера.
 */
final readonly class BitcoinRpcClient
{
    public function __construct(
        private HttpFactory $http,
        private string $url,
        private string $user,
        private string $password,
        private int $timeoutSeconds = 5,
        private int $connectTimeoutSeconds = 2,
        private int $retries = 1,
        private int $retryBackoffMs = 150,
    ) {}

    public function getBlockCount(): int
    {
        /** @var int|string $result */
        $result = $this->call('getblockcount', []);
        return (int) $result;
    }

    public function getBlockHash(int $height): string
    {
        $result = $this->call('getblockhash', [$height]);
        if (! is_string($result) || $result === '') {
            throw BlockSourceException::protocol("getblockhash({$height}) вернул пустой результат.");
        }
        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBlock(string $hash, int $verbosity = 2): array
    {
        $result = $this->call('getblock', [$hash, $verbosity]);
        if (! is_array($result)) {
            throw BlockSourceException::protocol("getblock({$hash}) вернул результат, отличный от массива.");
        }
        /** @var array<string, mixed> $result */
        return $result;
    }

    /**
     * @param list<mixed> $params
     * @return mixed
     */
    public function call(string $method, array $params): mixed
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
                ->retry($this->retries, $this->retryBackoffMs, throw: false)
                ->acceptJson()
                ->asJson()
                ->post($this->url, $payload);
        } catch (ConnectionException $e) {
            throw BlockSourceException::transport($e->getMessage(), $e);
        } catch (Throwable $e) {
            throw BlockSourceException::transport($e->getMessage(), $e);
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
            throw BlockSourceException::protocol(
                "Bitcoin RPC {$method} вернул статус {$response->status()} с телом, отличным от JSON."
            );
        }

        if (isset($body['error'])) {
            $error = is_array($body['error']) ? $body['error'] : ['message' => (string) $body['error']];
            $code = $error['code'] ?? 'unknown';
            $message = $error['message'] ?? 'unknown';
            throw BlockSourceException::protocol(
                "Ошибка Bitcoin RPC {$method} {$code}: {$message}"
            );
        }

        if (! array_key_exists('result', $body)) {
            throw BlockSourceException::protocol("В ответе Bitcoin RPC {$method} отсутствует 'result'.");
        }

        return $body['result'];
    }
}
