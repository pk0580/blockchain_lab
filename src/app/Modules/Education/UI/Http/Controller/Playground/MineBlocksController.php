<?php

declare(strict_types=1);

namespace App\Modules\Education\UI\Http\Controller\Playground;

use App\Modules\Education\Application\Contract\RegtestRpcClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * POST /api/playground/regtest/mine — генерирует N regtest-блоков. Если узел
 * подключён к встроенному кошельку, address берётся через getnewaddress;
 * иначе клиент обязан прислать `address` в теле.
 */
final readonly class MineBlocksController
{
    public function __construct(private RegtestRpcClient $rpc) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'blocks' => ['required', 'integer', 'min:1', 'max:50'],
            'address' => ['nullable', 'string', 'min:10', 'max:90'],
        ]);

        try {
            $address = $payload['address'] ?? $this->rpc->getNewAddress();
            if (! is_string($address) || $address === '') {
                return new JsonResponse([
                    'error' => [
                        'code' => 'address_required',
                        'message' => 'Regtest wallet is disabled; provide an `address` in the body.',
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $hashes = $this->rpc->generateToAddress($payload['blocks'], $address);
        } catch (RuntimeException $e) {
            return new JsonResponse([
                'error' => ['code' => 'mine_failed', 'message' => $e->getMessage()],
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'data' => [
                'mined' => count($hashes),
                'address' => $address,
                'hashes' => $hashes,
            ],
        ]);
    }
}
