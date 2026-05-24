<?php

declare(strict_types=1);

namespace App\Modules\Network\Infrastructure\Persistence\Eloquent\Mappers;

use App\Modules\Network\Domain\Entity\Chain;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use App\Modules\Network\Domain\ValueObject\ChainName;
use App\Modules\Network\Domain\ValueObject\ConfirmationRequirement;
use App\Modules\Network\Domain\ValueObject\NativeCurrency;
use App\Modules\Network\Domain\ValueObject\RpcEndpoint;
use App\Modules\Network\Domain\ValueObject\RpcKind;
use App\Modules\Network\Infrastructure\Persistence\Eloquent\Models\ChainModel;
use App\Modules\Network\Infrastructure\Persistence\Eloquent\Models\ChainRpcEndpointModel;
use DateTimeImmutable;

final class ChainMapper
{
    public function toDomain(ChainModel $row): Chain
    {
        /** @var list<RpcEndpoint> $endpoints */
        $endpoints = $row->endpoints
            ->map(fn (ChainRpcEndpointModel $e): RpcEndpoint => new RpcEndpoint(
                url: $e->url,
                kind: RpcKind::from($e->kind),
                priority: $e->priority,
                weight: $e->weight,
            ))
            ->all();

        return Chain::reconstitute(
            id: new ChainId($row->id),
            name: new ChainName($row->name),
            family: ChainFamily::from($row->family),
            nativeCurrency: new NativeCurrency($row->currency_symbol, $row->currency_decimals),
            confirmationRequirement: new ConfirmationRequirement(
                $row->required_confirmations,
                $row->max_reorg_depth,
            ),
            endpoints: $endpoints,
            enabled: $row->enabled,
            registeredAt: DateTimeImmutable::createFromInterface($row->registered_at),
        );
    }

    /**
     * @return array{
     *     id: string, name: string, family: string,
     *     currency_symbol: string, currency_decimals: int,
     *     required_confirmations: int, max_reorg_depth: int,
     *     enabled: bool, registered_at: \DateTimeImmutable
     * }
     */
    public function toRow(Chain $chain): array
    {
        return [
            'id' => $chain->id->value,
            'name' => (string) $chain->name,
            'family' => $chain->family->value,
            'currency_symbol' => $chain->nativeCurrency->symbol,
            'currency_decimals' => $chain->nativeCurrency->decimals,
            'required_confirmations' => $chain->confirmationRequirement->requiredConfirmations,
            'max_reorg_depth' => $chain->confirmationRequirement->maxReorgDepth,
            'enabled' => $chain->isEnabled(),
            'registered_at' => $chain->registeredAt,
        ];
    }

    /**
     * @return list<array{url: string, kind: string, priority: int, weight: int}>
     */
    public function endpointsToRows(Chain $chain): array
    {
        return array_map(
            fn (RpcEndpoint $e): array => [
                'url' => $e->url,
                'kind' => $e->kind->value,
                'priority' => $e->priority,
                'weight' => $e->weight,
            ],
            $chain->endpoints(),
        );
    }
}
