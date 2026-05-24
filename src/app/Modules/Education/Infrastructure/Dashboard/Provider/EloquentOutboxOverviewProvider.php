<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Dashboard\Provider;

use App\Modules\Education\Application\Contract\Dashboard\OutboxOverviewProvider;
use App\Modules\Education\Application\DTO\Dashboard\OutboxSection;
use App\Modules\Education\Infrastructure\Persistence\Eloquent\OutboxMessageLookupModel;
use App\Modules\Education\Infrastructure\Persistence\Eloquent\WebhookDeliveryLookupModel;
use Carbon\CarbonImmutable;

/**
 * Lag-индикатор: сколько outbox-сообщений ещё не уехало в шину + breakdown по
 * статусам deliveries. `oldestUnpublishedAt` — главный показатель того, что
 * `PublishOutboxJob` не справляется (растущая дельта → внимание).
 *
 * Хардкод-список статусов (pending/delivered/failed) выровнен с
 * `WebhookDeliveryStatus` enum'ом в Webhook::Domain.
 */
final readonly class EloquentOutboxOverviewProvider implements OutboxOverviewProvider
{
    public function load(): OutboxSection
    {
        $unpublished = OutboxMessageLookupModel::query()
            ->whereNull('published_at')
            ->count();

        // `min()` идёт через query builder и возвращает raw-значение из БД
        // (строка для timestamptz). Eloquent casts здесь не действуют — это
        // не загрузка модели. Парсим руками в CarbonImmutable.
        $oldestRaw = OutboxMessageLookupModel::query()
            ->whereNull('published_at')
            ->min('created_at');

        $oldestUnpublishedAt = is_string($oldestRaw) && $oldestRaw !== ''
            ? CarbonImmutable::parse($oldestRaw)->format(DATE_ATOM)
            : null;

        $pending = WebhookDeliveryLookupModel::query()->where('status', 'pending')->count();
        $delivered = WebhookDeliveryLookupModel::query()->where('status', 'delivered')->count();
        $failed = WebhookDeliveryLookupModel::query()->where('status', 'failed')->count();

        return new OutboxSection(
            unpublishedMessages: $unpublished,
            oldestUnpublishedAt: $oldestUnpublishedAt,
            deliveriesPending: $pending,
            deliveriesFailed: $failed,
            deliveriesDelivered: $delivered,
        );
    }
}
