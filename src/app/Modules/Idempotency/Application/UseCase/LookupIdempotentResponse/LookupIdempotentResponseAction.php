<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Application\UseCase\LookupIdempotentResponse;

use App\Modules\Idempotency\Domain\Contract\Clock;
use App\Modules\Idempotency\Domain\Contract\IdempotencyStore;
use App\Modules\Idempotency\Domain\Entity\IdempotencyRecord;
use App\Modules\Idempotency\Domain\Exception\IdempotencyConflictException;
use App\Modules\Idempotency\Domain\ValueObject\IdempotencyKey;
use App\Modules\Idempotency\Domain\ValueObject\RequestHash;

/**
 * Возвращает запись для replay'я, либо `null` если запись отсутствует или
 * просрочена. Бросает `IdempotencyConflictException` если тот же ключ был
 * использован с другим телом — это always-client-bug, не валидный replay.
 */
final readonly class LookupIdempotentResponseAction
{
    public function __construct(
        private IdempotencyStore $store,
        private Clock $clock,
    ) {}

    public function handle(IdempotencyKey $key, RequestHash $hash): ?IdempotencyRecord
    {
        $record = $this->store->find($key);
        if ($record === null) {
            return null;
        }
        if ($record->isExpired($this->clock->now())) {
            return null;
        }
        if (! $record->matches($hash)) {
            throw IdempotencyConflictException::for($key);
        }
        return $record;
    }
}
