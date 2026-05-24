<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller\Playground;

use App\Modules\Education\Application\Contract\RegtestRpcClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * GET /api/playground/regtest/mempool — текущий pool регтест-узла.
 * Возвращает list of txids + headers (count). Использует `getrawmempool false`
 * чтобы payload оставался компактным; verbose=true вытащит fee/size и т.д.
 */
final readonly class GetMempoolController
{
    public function __construct(private RegtestRpcClient $rpc) {}

    public function __invoke(): JsonResponse
    {
        try {
            $txids = $this->rpc->getRawMempool();
            $blockCount = $this->rpc->getBlockCount();
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'error' => ['code' => 'regtest_unavailable', 'message' => $e->getMessage()],
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'data' => [
                'block_count' => $blockCount,
                'mempool_size' => count($txids),
                'txids' => $txids,
            ],
        ]);
    }
}
