<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\UI\Console;

use App\Modules\BlockIngestion\Application\UseCase\ScanNextBlock\ScanNextBlockAction;
use App\Modules\BlockIngestion\Application\UseCase\ScanNextBlock\ScanNextBlockData;
use Illuminate\Console\Command;

final class ScanCommand extends Command
{
    /** @var string */
    protected $signature = 'scan:run
        {chain : ID блокчейна, например bitcoin-regtest}
        {--max=25 : Максимальное количество блоков для обработки за этот запуск}';

    /** @var string */
    protected $description = 'Запустить один цикл сканирования: обработать до N ожидающих блоков для блокчейна.';

    public function handle(ScanNextBlockAction $action): int
    {
        $result = $action->handle(new ScanNextBlockData(
            chainId: (string) $this->argument('chain'),
            maxBlocksPerTick: max(1, (int) $this->option('max')),
        ));

        $this->table(
            ['chain', 'blocks_scanned', 'matches_detected', 'last_scanned', 'head'],
            [[
                $result->chainId,
                $result->blocksScanned,
                $result->matchesDetected,
                $result->lastScannedHeight,
                $result->headHeight,
            ]],
        );

        return self::SUCCESS;
    }
}
