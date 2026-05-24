<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\UI\Http\Controller;

use App\Modules\Withdrawal\Application\UseCase\RequestWithdrawal\RequestWithdrawalAction;
use App\Modules\Withdrawal\Domain\Exception\ChainPausedException;
use App\Modules\Withdrawal\Domain\Exception\IdempotencyConflictException;
use App\Modules\Withdrawal\Domain\Exception\WithdrawalBroadcastFailedException;
use App\Modules\Withdrawal\UI\Http\Request\CreateWithdrawalRequest;
use App\Modules\Withdrawal\UI\Http\Resource\WithdrawalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * POST /api/v1/withdrawals
 *
 *  - 202 Accepted при новом запросе (withdrawal либо уже Broadcasted, либо
 *    Failed — клиент тянет статус повторным GET в Phase 6.3).
 *  - 200 OK при идемпотентном replay с тем же body.
 *  - 409 Conflict если Idempotency-Key переиспользован с другим payload.
 *  - 502 Bad Gateway если broadcast (или upstream) сорвался — withdrawal
 *    остаётся в БД со status=Failed.
 */
final readonly class CreateWithdrawalController
{
    public function __construct(private RequestWithdrawalAction $action) {}

    public function __invoke(CreateWithdrawalRequest $request): JsonResponse
    {
        try {
            $result = $this->action->handle($request->toDto());
        } catch (IdempotencyConflictException $e) {
            return new JsonResponse([
                'error' => [
                    'code' => 'idempotency_conflict',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_CONFLICT);
        } catch (ChainPausedException $e) {
            return new JsonResponse([
                'error' => [
                    'code' => 'chain_paused',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (WithdrawalBroadcastFailedException $e) {
            return new JsonResponse([
                'error' => [
                    'code' => 'broadcast_failed',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_BAD_GATEWAY);
        }

        $status = $result->reused ? Response::HTTP_OK : Response::HTTP_ACCEPTED;

        return new JsonResponse(
            data: ['data' => WithdrawalResource::toArray($result->withdrawal)],
            status: $status,
        );
    }
}
