<?php

declare(strict_types=1);

namespace App\Modules\Address\Infrastructure\Listener;

use App\Modules\Address\Domain\Event\AddressGenerated;
use App\Modules\BlockIngestion\Domain\Contract\AddressDirectory;

/**
 * Слушатель-мост: каждый новый адрес из этого модуля передается в
 * AddressDirectory, чтобы BlockIngestion мог сопоставить его, не импортируя
 * пространство имен Address.
 */
final readonly class RegisterAddressInDirectory
{
    public function __construct(private AddressDirectory $directory) {}

    public function handle(AddressGenerated $event): void
    {
        $this->directory->register($event->family, $event->address);
    }
}
