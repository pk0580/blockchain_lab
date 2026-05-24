<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Withdrawal\Domain\Entity\Withdrawal;
use App\Modules\Withdrawal\Domain\Repository\WithdrawalRepository;
use App\Modules\Withdrawal\Domain\ValueObject\IdempotencyKey;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalStatus;
use App\Modules\Withdrawal\Infrastructure\Persistence\Eloquent\Mappers\WithdrawalMapper;
use App\Modules\Withdrawal\Infrastructure\Persistence\Eloquent\Models\WithdrawalModel;
use DateTimeImmutable;

final readonly class EloquentWithdrawalRepository implements WithdrawalRepository
{
    public function __construct(private WithdrawalMapper $mapper) {}

    public function findById(WithdrawalId $id): ?Withdrawal
    {
        $row = WithdrawalModel::query()->find($id->value);
        return $row instanceof WithdrawalModel ? $this->mapper->toDomain($row) : null;
    }

    public function findByIdempotencyKey(IdempotencyKey $key): ?Withdrawal
    {
        $row = WithdrawalModel::query()
            ->where('idempotency_key', $key->value)
            ->first();
        return $row instanceof WithdrawalModel ? $this->mapper->toDomain($row) : null;
    }

    public function findActiveByChain(ChainId $chainId, int $limit): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, WithdrawalModel> $rows */
        $rows = WithdrawalModel::query()
            ->where('chain_id', $chainId->value)
            ->whereIn('status', [
                WithdrawalStatus::Broadcasted->value,
                WithdrawalStatus::Confirming->value,
            ])
            ->orderBy('broadcast_at')
            ->limit($limit)
            ->get();

        return array_values(array_map(
            fn (WithdrawalModel $row): Withdrawal => $this->mapper->toDomain($row),
            $rows->all(),
        ));
    }

    public function findStuckCandidates(
        ChainId $chainId,
        DateTimeImmutable $broadcastedAtOrBefore,
        int $limit,
    ): array {
        /** @var \Illuminate\Database\Eloquent\Collection<int, WithdrawalModel> $rows */
        $rows = WithdrawalModel::query()
            ->where('chain_id', $chainId->value)
            ->where('status', WithdrawalStatus::Broadcasted->value)
            ->where('broadcast_at', '<=', $broadcastedAtOrBefore)
            ->orderBy('broadcast_at')
            ->limit($limit)
            ->get();

        return array_values(array_map(
            fn (WithdrawalModel $row): Withdrawal => $this->mapper->toDomain($row),
            $rows->all(),
        ));
    }

    public function save(Withdrawal $withdrawal): void
    {
        $row = $this->mapper->toRow($withdrawal);
        WithdrawalModel::query()->updateOrInsert(
            ['id' => $withdrawal->id->value],
            $row,
        );
    }
}
