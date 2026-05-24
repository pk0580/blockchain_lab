<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Nonce;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\Withdrawal\Domain\Contract\NonceProbe;
use App\Modules\Withdrawal\Domain\ValueObject\HotAddress;
use App\Modules\Withdrawal\Domain\ValueObject\NonceValue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Спрашивает `eth_getTransactionCount(addr, 'pending')` — это next nonce,
 * который сеть ожидает увидеть в следующей транзакции. Pending включает tx
 * в mempool, что важно: если предыдущая withdrawal ещё не подтверждена, мы
 * должны взять nonce ПОСЛЕ неё, а не пере-использовать.
 *
 * Возвращает null при сетевой ошибке — allocator перейдёт на fallback (0).
 * Это сознательно: лучше потенциально создать nonce-конфликт (упрётся в
 * unique constraint), чем падать с ошибкой, когда история в нашей БД есть.
 */
final readonly class EvmNonceProbe implements NonceProbe
{
    public function __construct(
        private HttpFactory $http,
        private int $timeoutSeconds = 5,
        private int $connectTimeoutSeconds = 2,
        private int $retries = 1,
        private int $retryBackoffMs = 150,
    ) {}

    public function probe(Chain $chain, HotAddress $hot): ?NonceValue
    {
        $url = $this->httpUrl($chain);
        if ($url === null) {
            return null;
        }

        $payload = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'eth_getTransactionCount',
            'params' => [$hot->value, 'pending'],
        ];

        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->connectTimeout($this->connectTimeoutSeconds)
                ->retry($this->retries, $this->retryBackoffMs, throw: false)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (ConnectionException) {
            return null;
        } catch (Throwable) {
            return null;
        }

        /** @var array<string, mixed>|null $body */
        $body = $response->json();
        if (! is_array($body) || ! isset($body['result'])) {
            return null;
        }
        $hex = (string) $body['result'];
        $dec = $this->hexToInt($hex);
        if ($dec === null) {
            return null;
        }
        return new NonceValue($dec);
    }

    private function httpUrl(Chain $chain): ?string
    {
        foreach ($chain->endpoints() as $endpoint) {
            if ($endpoint->kind === RpcKind::Http) {
                return $endpoint->url;
            }
        }
        return null;
    }

    private function hexToInt(string $hex): ?int
    {
        $clean = strtolower(trim($hex));
        if ($clean === '' || $clean === '0x' || $clean === '0x0') {
            return 0;
        }
        if (str_starts_with($clean, '0x')) {
            $clean = substr($clean, 2);
        }
        if (! preg_match('/^[0-9a-f]+$/', $clean)) {
            return null;
        }
        // Nonce не превышает int64 в обозримом будущем — простой hexdec/intval достаточно.
        $intVal = hexdec($clean);
        return is_int($intVal) ? $intVal : (int) $intVal;
    }
}
