<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Webhook\Domain\Entity\OutboxMessage;
use App\Modules\Webhook\Domain\Repository\OutboxRepository;
use App\Modules\Webhook\Domain\ValueObject\OutboxMessageId;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Mappers\OutboxMessageMapper;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Models\OutboxMessageModel;

final readonly class EloquentOutboxRepository implements OutboxRepository
{
    public function __construct(private OutboxMessageMapper $mapper) {}

    public function findById(OutboxMessageId $id): ?OutboxMessage
    {
        $row = OutboxMessageModel::query()->find($id->value);
        return $row instanceof OutboxMessageModel ? $this->mapper->toDomain($row) : null;
    }

    public function findUnpublished(int $limit): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, OutboxMessageModel> $rows */
        $rows = OutboxMessageModel::query()
            ->whereNull('published_at')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        return array_values(array_map(
            fn (OutboxMessageModel $r) => $this->mapper->toDomain($r),
            $rows->all(),
        ));
    }

    public function save(OutboxMessage $message): void
    {
        $row = $this->mapper->toRow($message);
        OutboxMessageModel::query()->updateOrInsert(['id' => $message->id->value], $row);
    }
}
