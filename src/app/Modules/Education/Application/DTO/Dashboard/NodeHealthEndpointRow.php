<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\DTO\Dashboard;

/**
 * Одна строка в dashboard'е health'а: chain + endpoint + последнее наблюдение.
 * Статус строкой, а не enum — UI не должен знать про NodeHealth::Domain.
 */
final readonly class NodeHealthEndpointRow
{
    public function __construct(
        public string $chainId,
        public string $chainName,
        public string $chainFamily,
        public string $endpointUrl,
        public string $status,
        public ?int $headHeight,
        public ?int $latencyMs,
        public ?string $observedAt,
        public ?string $error,
    ) {}

    /**
     * @return array{chain_id:string, chain_name:string, chain_family:string, endpoint_url:string, status:string, head_height:?int, latency_ms:?int, observed_at:?string, error:?string}
     */
    public function toArray(): array
    {
        return [
            'chain_id' => $this->chainId,
            'chain_name' => $this->chainName,
            'chain_family' => $this->chainFamily,
            'endpoint_url' => $this->endpointUrl,
            'status' => $this->status,
            'head_height' => $this->headHeight,
            'latency_ms' => $this->latencyMs,
            'observed_at' => $this->observedAt,
            'error' => $this->error,
        ];
    }
}
