<?php

declare(strict_types=1);

namespace App\Modules\Address\UI\Console;

use App\Modules\Address\Application\UseCase\GenerateAddress\GenerateAddressAction;
use App\Modules\Address\Application\UseCase\GenerateAddress\GenerateAddressData;
use Illuminate\Console\Command;

final class GenerateAddressCommand extends Command
{
    /** @var string */
    protected $signature = 'address:generate
        {seed_id : HD seed id (UUID)}
        {family : bitcoin|evm|tron}
        {--wallet= : Optional wallet UUID to attach the address to}';

    /** @var string */
    protected $description = 'Derive the next address for a seed+family pair.';

    public function handle(GenerateAddressAction $action): int
    {
        $result = $action->handle(new GenerateAddressData(
            seedId: (string) $this->argument('seed_id'),
            family: (string) $this->argument('family'),
            walletId: $this->option('wallet') !== null ? (string) $this->option('wallet') : null,
        ));
        $this->table(
            ['id', 'family', 'address', 'path', 'wallet'],
            [[$result->id, $result->family, $result->address, $result->derivationPath, $result->walletId ?? '—']],
        );
        return self::SUCCESS;
    }
}
