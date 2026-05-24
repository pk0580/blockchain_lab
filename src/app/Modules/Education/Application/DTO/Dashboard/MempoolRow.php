<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\DTO\Dashboard;

/**
 * Per-chain mempool snapshot. `txCount` = null значит «не удалось получить»
 * (а не пустой mempool — пустой это 0).
 */
final readonly class MempoolRow
{
    public function __construct(
        public string $chainId,
        public string $chainName,
        public string $chainFamily,
        public ?int $txCount,
        public ?string $error,
    ) {}

    /**
     * @return array{chain_id:string, chain_name:string, chain_family:string, tx_count:?int, error:?string}
     */
    public function toArray(): array
    {
        return [
            'chain_id' => $this->chainId,
            'chain_name' => $this->chainName,
            'chain_family' => $this->chainFamily,
            'tx_count' => $this->txCount,
            'error' => $this->error,
        ];
    }
}
