<?php

declare(strict_types=1);

namespace App\Modules\Address\Application\UseCase\ValidateAddress;

use App\Modules\Address\Infrastructure\Cache\AddressValidationCache;
use App\Modules\Network\Domain\Contract\SigningClient;
use App\Modules\Network\Domain\ValueObject\ChainFamily;

/**
 * Проверяет валидность адреса через сервис подписи (signing service), кэшируя результат на 24 часа.
 * Пользовательские эндпоинты (например, "является ли этот адрес для вывода верным?") могут
 * вызывать это действие при каждом нажатии клавиши — Redis поглощает нагрузку.
 */
final readonly class ValidateAddressAction
{
    public function __construct(
        private SigningClient $signing,
        private AddressValidationCache $cache,
    ) {}

    public function handle(ValidateAddressData $data): bool
    {
        $family = ChainFamily::fromString($data->family);

        if (($cached = $this->cache->get($family, $data->address)) !== null) {
            return $cached;
        }

        $valid = $this->signing->isAddressValid($family, $data->address);
        $this->cache->put($family, $data->address, $valid);

        return $valid;
    }
}
