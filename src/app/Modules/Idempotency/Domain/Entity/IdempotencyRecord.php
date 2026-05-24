<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Domain\Entity;

use App\Modules\Idempotency\Domain\ValueObject\HttpMethod;
use App\Modules\Idempotency\Domain\ValueObject\HttpPath;
use App\Modules\Idempotency\Domain\ValueObject\IdempotencyKey;
use App\Modules\Idempotency\Domain\ValueObject\RequestHash;
use App\Modules\Idempotency\Domain\ValueObject\ResponseSnapshot;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Запись о сохранённом результате идемпотентного запроса. Иммутабельна:
 * однажды зафиксированный response никогда не переписывается — повторные
 * вызовы с тем же ключом или возвращают replay, или эскалируют конфликт.
 *
 * `expiresAt` — момент, после которого запись игнорируется как «отсутствующая».
 * Cleanup-job физически удаляет такие строки.
 */
final readonly class IdempotencyRecord
{
    public function __construct(
        public IdempotencyKey $key,
        public RequestHash $requestHash,
        public HttpMethod $method,
        public HttpPath $path,
        public ResponseSnapshot $response,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
    ) {
        if ($expiresAt <= $createdAt) {
            throw new InvalidArgumentException(
                "IdempotencyRecord expiresAt must be strictly after createdAt."
            );
        }
    }

    public function matches(RequestHash $hash): bool
    {
        return $this->requestHash->equals($hash);
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }
}
