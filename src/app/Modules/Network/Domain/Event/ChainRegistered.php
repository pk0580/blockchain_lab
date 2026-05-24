<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Event;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

final readonly class ChainRegistered
{
    public function __construct(
        public ChainId $chainId,
        public ChainFamily $family,
        public DateTimeImmutable $occurredAt,
    ) {}
}
