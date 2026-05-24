<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller\Playground;

use App\Modules\Education\Application\Contract\RegtestRpcClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * POST /api/playground/regtest/invalidate-tip — выбрасывает текущий best
 * block через `invalidateblock`. Если в теле передан явный `hash` — invalidate
 * его (для invalidate'а более глубокого блока).
 */
final readonly class InvalidateTipController
{
    public function __construct(private RegtestRpcClient $rpc) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'hash' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ]);

        try {
            $hash = $payload['hash'] ?? $this->rpc->getBestBlockHash();
            $this->rpc->invalidateBlock($hash);
            $newTip = $this->rpc->getBestBlockHash();
            $newHeight = $this->rpc->getBlockCount();
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'error' => ['code' => 'invalidate_failed', 'message' => $e->getMessage()],
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'data' => [
                'invalidated_hash' => $hash,
                'new_tip_hash' => $newTip,
                'new_block_count' => $newHeight,
            ],
        ]);
    }
}
