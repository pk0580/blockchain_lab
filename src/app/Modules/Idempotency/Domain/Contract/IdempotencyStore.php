<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Domain\Contract;

use App\Modules\Idempotency\Domain\Entity\IdempotencyRecord;
use App\Modules\Idempotency\Domain\ValueObject\IdempotencyKey;
use DateTimeImmutable;

/**
 * Хранилище записей идемпотентности. Контракт умышленно узкий:
 *
 * - `find` — поднимает запись по ключу. Expired записи возвращаются «как есть»;
 *   Application слой сам решает игнорировать ли их.
 * - `save` — upsert по ключу. Тот же key + другой hash должен молча перезаписать
 *   только если предыдущая запись expired. Логика конфликта живёт в Application.
 * - `deleteExpired` — освобождает таблицу. Возвращает количество удалённых строк
 *   для observability.
 */
interface IdempotencyStore
{
    public function find(IdempotencyKey $key): ?IdempotencyRecord;

    public function save(IdempotencyRecord $record): void;

    public function deleteExpired(DateTimeImmutable $now): int;
}
