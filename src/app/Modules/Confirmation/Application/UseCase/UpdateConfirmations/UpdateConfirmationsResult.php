<?php

declare(strict_types=1);

namespace App\Modules\Confirmation\Application\UseCase\UpdateConfirmations;

final readonly class UpdateConfirmationsResult
{
    public function __construct(
        public string $chainId,
        public int $rowsExamined,
        public int $rowsConfirming,
        public int $rowsConfirmed,
        public int $rowsFinalized,
    ) {}
}
