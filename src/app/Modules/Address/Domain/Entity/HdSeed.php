<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\Entity;

use App\Modules\Address\Domain\Event\HdSeedCreated;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Address\Domain\ValueObject\HdSeedReference;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DateTimeImmutable;

/**
 * Корень агрегата (Aggregate root) для мастер-сида HD, зарегистрированного в сервисе подписи.
 *
 * ВАЖНО: эта сущность НЕ содержит материала ключей. Мнемоника / мастер-ключ
 * находятся исключительно внутри Go-сервиса подписи; мы храним непрозрачную
 * {@see HdSeedReference}, которую сервис подписи использует для их поиска.
 */
final class HdSeed
{
    /** @var list<object> */
    private array $pendingEvents = [];

    private function __construct(
        public readonly HdSeedId $id,
        public readonly HdSeedReference $reference,
        public readonly ?ChainFamily $family,         // null = несколько семейств (Фаза 9+)
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        HdSeedId $id,
        HdSeedReference $reference,
        ?ChainFamily $family,
        DateTimeImmutable $now,
    ): self {
        $seed = new self($id, $reference, $family, $now);
        $seed->pendingEvents[] = new HdSeedCreated($id, $reference, $now);
        return $seed;
    }

    public static function reconstitute(
        HdSeedId $id,
        HdSeedReference $reference,
        ?ChainFamily $family,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $reference, $family, $createdAt);
    }

    public function supportsFamily(ChainFamily $family): bool
    {
        return $this->family === null || $this->family === $family;
    }

    /**
     * @return list<object>
     */
    public function pullPendingEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];
        return $events;
    }
}
