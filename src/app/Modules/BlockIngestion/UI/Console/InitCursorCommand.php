<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\UI\Console;

use App\Modules\BlockIngestion\Application\UseCase\InitChainCursor\InitChainCursorAction;
use App\Modules\BlockIngestion\Application\UseCase\InitChainCursor\InitChainCursorData;
use Illuminate\Console\Command;

final class InitCursorCommand extends Command
{
    /** @var string */
    protected $signature = 'scan:init {chain : Chain id, e.g. bitcoin-regtest}';

    /** @var string */
    protected $description = 'Initialise the scan cursor for a chain (baseline at the current head).';

    public function handle(InitChainCursorAction $action): int
    {
        $cursor = $action->handle(new InitChainCursorData(
            chainId: (string) $this->argument('chain'),
        ));

        $this->table(
            ['chain', 'last_scanned', 'last_seen_head'],
            [[$cursor->chainId->value, $cursor->lastScannedHeight()->value, $cursor->lastSeenHeadHeight()->value]],
        );

        return self::SUCCESS;
    }
}
