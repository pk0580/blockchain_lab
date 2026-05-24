<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Application\UseCase\MarkStuckWithdrawals;

final readonly class MarkStuckWithdrawalsResult
{
    /**
     * @param list<string> $markedIds
     */
    public function __construct(public array $markedIds) {}

    public function count(): int
    {
        return count($this->markedIds);
    }
}
