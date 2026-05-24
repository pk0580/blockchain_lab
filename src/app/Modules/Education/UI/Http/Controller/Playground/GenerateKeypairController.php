<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller\Playground;

use App\Modules\Education\Application\Contract\PlaygroundClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * POST /api/playground/keypair — proxy в signing-svc /v1/playground/keypair.
 * Принимает optional `mnemonic` (12+ слов BIP-39); если отсутствует — Go
 * генерирует новый со 128-битной энтропией.
 */
final readonly class GenerateKeypairController
{
    public function __construct(private PlaygroundClient $client) {}

    public function __invoke(Request $request): JsonResponse
    {
        $mnemonic = $request->input('mnemonic');
        if ($mnemonic !== null && ! is_string($mnemonic)) {
            return new JsonResponse([
                'error' => ['code' => 'bad_request', 'message' => 'mnemonic must be a string'],
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = $this->client->generateKeypair($mnemonic);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'error' => ['code' => 'keypair_failed', 'message' => $e->getMessage()],
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['data' => $data]);
    }
}
