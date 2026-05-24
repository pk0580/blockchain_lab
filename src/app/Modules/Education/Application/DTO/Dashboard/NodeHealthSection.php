<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\DTO\Dashboard;

final readonly class NodeHealthSection
{
    /**
     * @param  list<NodeHealthEndpointRow>  $rows
     */
    public function __construct(public array $rows) {}

    /**
     * @return array{rows: list<array{chain_id:string, chain_name:string, chain_family:string, endpoint_url:string, status:string, head_height:?int, latency_ms:?int, observed_at:?string, error:?string}>}
     */
    public function toArray(): array
    {
        return [
            'rows' => array_map(fn (NodeHealthEndpointRow $r) => $r->toArray(), $this->rows),
        ];
    }
}
