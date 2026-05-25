<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Domain\Repository;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Withdrawal\Domain\Entity\Withdrawal;
use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use DateTimeImmutable;

/**
 * Узкий aggregate-scoped репозиторий: point-lookups плюс два списковых запроса
 * для polling-job'ов (confirmations + stuck).
 * Это сознательно остаётся write-репозиторием: возвращаем aggregate'ы, не DTO.
 * Полноценный CQRS-read должен жить в отдельном `WithdrawalReadRepository`.
 */
interface WithdrawalRepository
{
    public function findById(WithdrawalId $id): ?Withdrawal;

    public function findByIdempotencyKey(IdempotencyKey $key): ?Withdrawal;

    /**
     * Активные withdrawals на сети (Broadcasted | Confirming) для polling-job'а
     * обновления подтверждений. Возвращается отсортированным по broadcast_at ASC,
     * чтобы первыми обработать те, что ждут дольше.
     *
     * @return list<Withdrawal>
     */
    public function findActiveByChain(ChainId $chainId, int $limit): array;

    /**
     * Кандидаты на пометку Stuck: статус Broadcasted, broadcast_at <= $broadcastedAtOrBefore.
     * Используется WatchStuckWithdrawalsJob.
     *
     * @return list<Withdrawal>
     */
    public function findStuckCandidates(
        ChainId $chainId,
        DateTimeImmutable $broadcastedAtOrBefore,
        int $limit,
    ): array;

    public function save(Withdrawal $withdrawal): void;
}
