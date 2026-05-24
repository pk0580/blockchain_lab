<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller\Playground;

use App\Modules\Education\Application\Contract\PlaygroundClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * POST /api/playground/sign — proxy в signing-svc /v1/playground/sign.
 * Body: { private_key_hex (64 hex chars), message_hex (произвольная длина) }.
 */
final readonly class SignMessageController
{
    public function __construct(private PlaygroundClient $client) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'private_key_hex' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
            'message_hex' => ['required', 'string', 'regex:/^[a-fA-F0-9]*$/'],
        ]);

        try {
            $data = $this->client->sign($payload['private_key_hex'], $payload['message_hex']);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'error' => ['code' => 'sign_failed', 'message' => $e->getMessage()],
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['data' => $data]);
    }
}
