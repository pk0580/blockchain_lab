<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;

interface FinalityPolicy
{
    public function requirement(): ConfirmationRequirement;

    public function isFinal(int $observedConfirmations): bool;
}
