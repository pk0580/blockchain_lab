<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Http;

use App\Modules\Education\Application\Contract\PlaygroundClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

final readonly class HttpPlaygroundClient implements PlaygroundClient
{
    public function __construct(
        private HttpFactory $http,
        private string $baseUrl,
        private string $bearerToken,
        private int $timeoutSeconds = 10,
    ) {}

    public function generateKeypair(?string $mnemonic): array
    {
        $body = $mnemonic !== null ? ['mnemonic' => $mnemonic] : [];
        return $this->post('/v1/playground/keypair', $body);
    }

    public function sign(string $privateKeyHex, string $messageHex): array
    {
        return $this->post('/v1/playground/sign', [
            'private_key_hex' => $privateKeyHex,
            'message_hex' => $messageHex,
        ]);
    }

    public function decode(string $chain, string $rawHex): array
    {
        return $this->post('/v1/playground/decode', [
            'chain' => $chain,
            'raw_hex' => $rawHex,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        $client = $this->http
            ->baseUrl($this->baseUrl)
            ->timeout($this->timeoutSeconds)
            ->withToken($this->bearerToken)
            ->acceptJson();

        // PHP сериализует пустой массив как JSON-array `[]`, а Go-сервис
        // unmarshal'ит в struct и падает на "cannot unmarshal array into Go
        // value of type server.playgroundKeypairReq". Шлём `{}` явно.
        $response = $body === []
            ? $client->withBody('{}', 'application/json')->post($path)
            : $client->asJson()->post($path, $body);

        if (! $response->successful()) {
            /** @var array<string, mixed> $err */
            $err = $response->json() ?? [];
            $code = is_array($err['error'] ?? null) && isset($err['error']['code']) && is_string($err['error']['code'])
                ? $err['error']['code']
                : 'playground_failed';
            $message = is_array($err['error'] ?? null) && isset($err['error']['message']) && is_string($err['error']['message'])
                ? $err['error']['message']
                : 'signing-svc returned an error';
            throw new RuntimeException("playground_failed[{$code}]: {$message}", $response->status());
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        return $data;
    }
}
