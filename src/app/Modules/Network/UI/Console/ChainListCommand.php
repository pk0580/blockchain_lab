<?php

declare(strict_types=1);

namespace App\Modules\Network\UI\Console;

use App\Modules\Network\Application\Query\ListChains\ListChainsHandler;
use Illuminate\Console\Command;

final class ChainListCommand extends Command
{
    /** @var string */
    protected $signature = 'chain:list {--enabled : Only list enabled chains}';

    /** @var string */
    protected $description = 'List registered blockchain networks.';

    public function handle(ListChainsHandler $handler): int
    {
        $rows = [];
        foreach ($handler->handle(onlyEnabled: (bool) $this->option('enabled')) as $view) {
            $rows[] = [
                $view->id,
                $view->family,
                $view->currencySymbol,
                $view->requiredConfirmations.' / '.$view->maxReorgDepth,
                $view->enabled ? 'yes' : 'no',
                count($view->endpoints),
            ];
        }

        $this->table(
            ['id', 'family', 'currency', 'conf / max-reorg', 'enabled', 'endpoints'],
            $rows,
        );

        return self::SUCCESS;
    }
}
