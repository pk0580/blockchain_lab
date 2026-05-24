<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Domain\Contract;

use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\NodeHealth\Domain\Exception\UnsupportedProbeFamilyException;

interface EndpointHealthProbeRegistry
{
    /**
     * @throws UnsupportedProbeFamilyException если probe для семейства не зарегистрирован.
     */
    public function for(ChainFamily $family): EndpointHealthProbe;
}
