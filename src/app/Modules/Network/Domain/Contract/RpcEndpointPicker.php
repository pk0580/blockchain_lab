<?php

declare(strict_types=1);

namespace App\Modules\Network\Domain\Contract;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\NoRpcEndpointException;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;

/**
 * Узкий read-only port: «дай мне URL endpoint'а нужного kind'а для chain».
 *
 * Реализация по умолчанию (Network::Infrastructure\Picker\FirstHttpEndpointPicker)
 * возвращает первый endpoint подходящего kind в порядке регистрации chain'а.
 *
 * NodeHealth::Infrastructure\Picker\HealthBasedRpcEndpointPicker (Phase 7.1)
 * перебивает default через ServiceProvider — выбирает первый Healthy/Unknown
 * endpoint, пропуская Unhealthy.
 *
 * Контракт намеренно НЕ принимает `EndpointStatus` или другие NodeHealth-VO —
 * Network не зависит от NodeHealth, picker остаётся black-box со стороны Domain.
 */
interface RpcEndpointPicker
{
    /**
     * @throws NoRpcEndpointException когда у chain нет endpoint'а нужного kind'а.
     */
    public function pick(Chain $chain, RpcKind $kind = RpcKind::Http): RpcEndpoint;
}
