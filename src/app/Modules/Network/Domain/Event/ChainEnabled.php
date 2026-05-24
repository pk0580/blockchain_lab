<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Event;

use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;

final readonly class ChainEnabled
{
    public function __construct(
        public ChainId $chainId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
