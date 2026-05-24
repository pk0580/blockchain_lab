<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Infrastructure\Persistence\Eloquent;

use App\Modules\Idempotency\Domain\Contract\IdempotencyStore;
use App\Modules\Idempotency\Domain\Entity\IdempotencyRecord;
use App\Modules\Idempotency\Domain\ValueObject\HttpMethod;
use App\Modules\Idempotency\Domain\ValueObject\HttpPath;
use App\Modules\Idempotency\Domain\ValueObject\IdempotencyKey;
use App\Modules\Idempotency\Domain\ValueObject\RequestHash;
use App\Modules\Idempotency\Domain\ValueObject\ResponseSnapshot;
use DateTimeImmutable;

final class EloquentIdempotencyStore implements IdempotencyStore
{
    public function find(IdempotencyKey $key): ?IdempotencyRecord
    {
        $row = IdempotencyKeyModel::query()->find($key->value);
        if ($row === null) {
            return null;
        }

        return new IdempotencyRecord(
            key: new IdempotencyKey($row->key),
            requestHash: new RequestHash($row->request_hash),
            method: new HttpMethod($row->method),
            path: new HttpPath($row->path),
            response: new ResponseSnapshot(
                status: $row->response_status,
                body: $row->response_body,
            ),
            createdAt: DateTimeImmutable::createFromInterface($row->created_at),
            expiresAt: DateTimeImmutable::createFromInterface($row->expires_at),
        );
    }

    public function save(IdempotencyRecord $record): void
    {
        IdempotencyKeyModel::query()->updateOrCreate(
            ['key' => $record->key->value],
            [
                'request_hash' => $record->requestHash->value,
                'method' => $record->method->value,
                'path' => $record->path->value,
                'response_status' => $record->response->status,
                'response_body' => $record->response->body,
                'created_at' => $record->createdAt,
                'expires_at' => $record->expiresAt,
            ],
        );
    }

    public function deleteExpired(DateTimeImmutable $now): int
    {
        return IdempotencyKeyModel::query()
            ->where('expires_at', '<=', $now)
            ->delete();
    }
}
