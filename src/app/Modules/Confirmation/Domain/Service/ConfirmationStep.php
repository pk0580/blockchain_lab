<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Domain\Service;

use App\Modules\Confirmation\Domain\ValueObject\ConfirmationOutcome;

final readonly class ConfirmationStep
{
    public function __construct(
        public int $confirmations,
        public ConfirmationOutcome $outcome,
    ) {}
}
