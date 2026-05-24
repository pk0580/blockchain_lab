<?php

declare(strict_types=1);

namespace App\Modules\Address\Domain\Event;

use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\Address\Domain\ValueObject\HdSeedReference;
use DateTimeImmutable;

final readonly class HdSeedCreated
{
    public function __construct(
        public HdSeedId $seedId,
        public HdSeedReference $reference,
        public DateTimeImmutable $occurredAt,
    ) {}
}
