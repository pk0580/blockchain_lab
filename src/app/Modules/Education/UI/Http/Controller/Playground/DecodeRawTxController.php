<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller\Playground;

use App\Modules\Education\Application\Contract\PlaygroundClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * POST /api/playground/decode — proxy в signing-svc /v1/playground/decode.
 * Body: { chain: "bitcoin"|"ethereum", raw_hex }.
 */
final readonly class DecodeRawTxController
{
    public function __construct(private PlaygroundClient $client) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'chain' => ['required', 'string', 'in:bitcoin,ethereum'],
            'raw_hex' => ['required', 'string', 'regex:/^(0x)?[a-fA-F0-9]+$/'],
        ]);

        try {
            $data = $this->client->decode($payload['chain'], $payload['raw_hex']);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'error' => ['code' => 'decode_failed', 'message' => $e->getMessage()],
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['data' => $data]);
    }
}
