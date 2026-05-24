<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller\Playground;

use App\Modules\Education\Application\Contract\RegtestRpcClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * GET /api/playground/regtest/state — даёт UI достаточно данных, чтобы
 * нарисовать «текущую цепочку»: высота, hash вершины, hash'и последних 5
 * блоков. Цена — 6 RPC-вызовов; для playground'а это OK.
 */
final readonly class GetRegtestStateController
{
    public function __construct(private RegtestRpcClient $rpc) {}

    public function __invoke(): JsonResponse
    {
        try {
            $tipHash = $this->rpc->getBestBlockHash();
            $count = $this->rpc->getBlockCount();
            $recent = $this->walkBack($tipHash, 5);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'error' => ['code' => 'regtest_unavailable', 'message' => $e->getMessage()],
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'data' => [
                'block_count' => $count,
                'tip_hash' => $tipHash,
                'recent' => $recent,
            ],
        ]);
    }

    /**
     * @return list<array{height:int, hash:string, parent_hash:string}>
     */
    private function walkBack(string $tipHash, int $depth): array
    {
        $out = [];
        $current = $tipHash;
        for ($i = 0; $i < $depth && $current !== ''; $i++) {
            $block = $this->rpc->getBlock($current, 1);
            $height = (int) ($block['height'] ?? 0);
            $parent = (string) ($block['previousblockhash'] ?? '');
            $out[] = [
                'height' => $height,
                'hash' => $current,
                'parent_hash' => $parent,
            ];
            $current = $parent;
        }
        return $out;
    }
}
