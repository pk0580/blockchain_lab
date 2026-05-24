<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\DTO\Dashboard;

final readonly class MempoolSection
{
    /**
     * @param  list<MempoolRow>  $rows
     */
    public function __construct(public array $rows) {}

    /**
     * @return array{rows: list<array{chain_id:string, chain_name:string, chain_family:string, tx_count:?int, error:?string}>}
     */
    public function toArray(): array
    {
        return [
            'rows' => array_map(fn (MempoolRow $r) => $r->toArray(), $this->rows),
        ];
    }
}
