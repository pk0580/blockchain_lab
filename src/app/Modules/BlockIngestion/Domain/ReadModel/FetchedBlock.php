<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\ReadModel;

use App\Modules\BlockIngestion\Domain\ValueObject\Currency;
use App\Modules\Network\Domain\ValueObject\BlockHash;
use App\Modules\Network\Domain\ValueObject\BlockHeight;
use DateTimeImmutable;

/**
 * Adapter-shaped block payload. Adapters fill this from RPC results so the
 * Application layer never sees raw RPC arrays. Outputs are flattened to
 * (toAddress, amount) per-tx — the scanner only cares about credits to
 * our watched addresses, not about input shapes.
 */
final readonly class FetchedBlock
{
    /**
     * @param list<FetchedTransaction> $transactions
     */
    public function __construct(
        public BlockHeight $height,
        public BlockHash $hash,
        public BlockHash $parentHash,
        public DateTimeImmutable $timestamp,
        public Currency $currency,
        public array $transactions,
    ) {}
}
