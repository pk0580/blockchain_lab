<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Registry;

use App\Modules\Network\Domain\Contract\FinalityPolicy;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;

final readonly class ConfirmationBasedFinality implements FinalityPolicy
{
    public function __construct(private ConfirmationRequirement $requirement) {}

    public function requirement(): ConfirmationRequirement
    {
        return $this->requirement;
    }

    public function isFinal(int $observedConfirmations): bool
    {
        return $this->requirement->isFinal($observedConfirmations);
    }
}
