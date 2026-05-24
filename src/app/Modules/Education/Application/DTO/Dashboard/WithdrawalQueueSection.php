<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\DTO\Dashboard;

final readonly class WithdrawalQueueSection
{
    /**
     * @param  array<string,int>  $countsByStatus  — заявлены все статусы из state-machine
     * @param  list<WithdrawalRow>  $recent
     */
    public function __construct(
        public array $countsByStatus,
        public array $recent,
    ) {}

    /**
     * @return array{counts_by_status:array<string,int>, recent:list<array<string,mixed>>}
     */
    public function toArray(): array
    {
        return [
            'counts_by_status' => $this->countsByStatus,
            'recent' => array_map(fn (WithdrawalRow $r) => $r->toArray(), $this->recent),
        ];
    }
}
