<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Picker;

use App\Modules\Network\Domain\Contract\RpcEndpointPicker;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\NoRpcEndpointException;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;

/**
 * Дефолтная реализация picker'а: первый endpoint нужного kind'а в порядке
 * регистрации. Используется когда NodeHealth не подключен (или для unit-тестов).
 *
 * При наличии нескольких endpoint'ов с одинаковым `priority` тиралий побеждает
 * первый зарегистрированный — Phase 9 добавит weighted round-robin.
 */
final readonly class FirstHttpEndpointPicker implements RpcEndpointPicker
{
    public function pick(Chain $chain, RpcKind $kind = RpcKind::Http): RpcEndpoint
    {
        foreach ($chain->endpoints() as $endpoint) {
            if ($endpoint->kind === $kind) {
                return $endpoint;
            }
        }
        throw NoRpcEndpointException::forChain($chain->id, $kind);
    }
}
