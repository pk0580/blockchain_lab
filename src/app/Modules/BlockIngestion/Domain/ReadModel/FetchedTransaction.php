<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Domain\ReadModel;

use App\Modules\Network\Domain\ValueObject\TxHash;

final readonly class FetchedTransaction
{
    /**
     * @param list<FetchedOutput> $outputs
     */
    public function __construct(
        public TxHash $txHash,
        public ?string $fromAddress,
        public array $outputs,
    ) {}
}
