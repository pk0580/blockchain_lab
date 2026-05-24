<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Signer;

use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\Exception\SigningClientException;
use App\Modules\Network\Domain\ValueObject\Address;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\SignedRawTx;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * HTTP-реализация {@see SigningClient}. Взаимодействует с изолированным сервисом
 * подписания на Go через общий bearer-токен (Фаза 2). Таймауты, повторные попытки и
 * отображение ошибок (наподобие автоматического размыкателя цепи / circuit-breaker) находятся здесь,
 * чтобы слой Domain никогда не видел типы Laravel HTTP.
 */
final readonly class HttpSigningClient implements SigningClient
{
    public function __construct(
        private HttpFactory $http,
        private string $baseUrl,
        private string $bearerToken,
        private int $timeoutSeconds = 5,
        private int $connectTimeoutSeconds = 2,
        private int $retries = 2,
        private int $retryBackoffMs = 150,
    ) {}

    public function ensureSeed(string $reference, ?string $importMnemonic = null): bool
    {
        $payload = ['reference' => $reference];
        if ($importMnemonic !== null) {
            $payload['mnemonic'] = $importMnemonic;
        }

        $response = $this->post('/v1/seeds', $payload);
        $data = $this->decode($response);

        return (bool) ($data['created'] ?? false);
    }

    public function deriveAddress(string $seedReference, ChainFamily $family, string $path): Address
    {
        $response = $this->post('/v1/addresses/derive', [
            'seed_reference' => $seedReference,
            'family' => $family->value,
            'path' => $path,
        ]);
        $data = $this->decode($response);
        $address = $data['address'] ?? null;
        if (! is_string($address) || $address === '') {
            throw new SigningClientException('Сервис подписания не вернул адрес.');
        }
        return new Address($address);
    }

    public function isAddressValid(ChainFamily $family, string $address): bool
    {
        $response = $this->post('/v1/addresses/validate', [
            'family' => $family->value,
            'address' => $address,
        ]);
        $data = $this->decode($response);
        return (bool) ($data['valid'] ?? false);
    }

    public function signRawTx(
        ChainFamily $family,
        string $seedReference,
        string $path,
        string $rawHex,
        array $extra = [],
    ): SignedRawTx {
        $payload = array_merge([
            'family' => $family->value,
            'seed_reference' => $seedReference,
            'path' => $path,
            'raw_hex' => $rawHex,
        ], $extra);

        $response = $this->post('/v1/tx/sign', $payload);
        $data = $this->decode($response);
        $signed = $data['signed_hex'] ?? null;
        if (! is_string($signed) || $signed === '') {
            throw new SigningClientException('Сервис подписания не вернул signed_hex.');
        }
        return new SignedRawTx($family, $signed);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $path, array $payload): Response
    {
        try {
            return $this->http
                ->baseUrl($this->baseUrl)
                ->withToken($this->bearerToken)
                ->timeout($this->timeoutSeconds)
                ->connectTimeout($this->connectTimeoutSeconds)
                ->retry($this->retries, $this->retryBackoffMs, throw: false)
                ->acceptJson()
                ->asJson()
                ->post($path, $payload);
        } catch (ConnectionException $e) {
            throw SigningClientException::transport($e->getMessage(), $e);
        } catch (Throwable $e) {
            throw SigningClientException::transport($e->getMessage(), $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        if ($response->successful()) {
            /** @var array<string, mixed> $data */
            $data = $response->json();
            return $data;
        }

        /** @var array<string, mixed>|null $body */
        $body = $response->json();
        $error = is_array($body) && isset($body['error']) && is_array($body['error']) ? $body['error'] : [];

        throw SigningClientException::http(
            status: $response->status(),
            code: (string) ($error['code'] ?? 'unknown'),
            message: (string) ($error['message'] ?? $response->body()),
        );
    }
}
