<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Infrastructure\Picker;

use App\Modules\Network\Domain\Contract\RpcEndpointPicker;
use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\Exception\NoRpcEndpointException;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\NodeHealth\Domain\Contract\EndpointHealthRegistry;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointKey;
use App\Modules\NodeHealth\Domain\ValueObject\EndpointStatus;

/**
 * Picker, который перебирает endpoint'ы chain'а и возвращает первый usable
 * (Healthy → Degraded → Unknown — в этом приоритете). Endpoint'ы со статусом
 * `Unhealthy` пропускаются.
 *
 * Если все endpoint'ы оказались Unhealthy — бросаем `NoRpcEndpointException`,
 * чтобы caller получил 503 (а не выбор «лучший из плохих»).
 */
final readonly class HealthBasedRpcEndpointPicker implements RpcEndpointPicker
{
    public function __construct(private EndpointHealthRegistry $registry) {}

    public function pick(Chain $chain, RpcKind $kind = RpcKind::Http): RpcEndpoint
    {
        $candidates = [];
        foreach ($chain->endpoints() as $endpoint) {
            if ($endpoint->kind !== $kind) {
                continue;
            }
            $status = $this->registry->status(new EndpointKey($chain->id, $endpoint->url));
            if (! $status->isUsable()) {
                continue;
            }
            $candidates[] = [$endpoint, $status];
        }

        if ($candidates === []) {
            throw NoRpcEndpointException::forChain(
                $chain->id,
                $kind,
                'all endpoints are unhealthy',
            );
        }

        // Сортировка по приоритету Healthy → Degraded → Unknown. Стабильная:
        // в пределах одного статуса берём первый по порядку регистрации.
        usort(
            $candidates,
            fn (array $a, array $b): int => $this->rank($a[1]) <=> $this->rank($b[1]),
        );

        return $candidates[0][0];
    }

    private function rank(EndpointStatus $status): int
    {
        return match ($status) {
            EndpointStatus::Healthy => 0,
            EndpointStatus::Degraded => 1,
            EndpointStatus::Unknown => 2,
            EndpointStatus::Unhealthy => 3,
        };
    }
}
