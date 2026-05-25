<?php

declare(strict_types=1);

namespace App\Modules\Address\UI\Console;

use App\Modules\Address\Application\UseCase\CreateHdSeed\CreateHdSeedAction;
use App\Modules\Address\Application\UseCase\CreateHdSeed\CreateHdSeedData;
use Illuminate\Console\Command;

/**
 * CLI-команда первоначальной настройки prod: создать (или импортировать) HD-сид.
 *
 * Типичные сценарии — см. GUIDE.md, Урок 2, раздел «Ссылка на ключ»:
 *
 *   php artisan address:seed:create prod-hot-001 --family=evm
 *   php artisan address:seed:create prod-hot-001 --family=evm --mnemonic="...12 words..."
 *
 * Сама команда тонкая: только парсит аргументы и зовёт {@see CreateHdSeedAction}.
 *
 * @see \GUIDE.md  Урок 2 (#урок-2--ключи-адреса-и-hd-кошельки)
 */
final class CreateHdSeedCommand extends Command
{
    /** @var string */
    protected $signature = 'address:seed:create
        {reference : Opaque reference, [a-zA-Z0-9_-]{4,64}}
        {--family= : bitcoin|evm|tron, or omit for multi-family}
        {--mnemonic= : Import an existing BIP-39 mnemonic (default: generate)}';

    /** @var string */
    protected $description = 'Create or import an HD seed in the signing service.';

    public function handle(CreateHdSeedAction $action): int
    {
        $id = $action->handle(new CreateHdSeedData(
            reference: (string) $this->argument('reference'),
            family: $this->option('family') !== null ? (string) $this->option('family') : null,
            importMnemonic: $this->option('mnemonic') !== null ? (string) $this->option('mnemonic') : null,
        ));
        $this->info("HdSeed created (id={$id->value}).");
        return self::SUCCESS;
    }
}
