<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Application\UseCase\UpdateConfirmations;

final readonly class UpdateConfirmationsData
{
    public function __construct(public string $chainId) {}
}
