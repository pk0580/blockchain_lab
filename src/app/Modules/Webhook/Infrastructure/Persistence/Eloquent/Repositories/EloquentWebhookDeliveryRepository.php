<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Webhook\Domain\Entity\WebhookDelivery;
use App\Modules\Webhook\Domain\Repository\WebhookDeliveryRepository;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryId;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryStatus;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Mappers\WebhookDeliveryMapper;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Models\WebhookDeliveryModel;
use DateTimeImmutable;

final readonly class EloquentWebhookDeliveryRepository implements WebhookDeliveryRepository
{
    public function __construct(private WebhookDeliveryMapper $mapper) {}

    public function findById(WebhookDeliveryId $id): ?WebhookDelivery
    {
        $row = WebhookDeliveryModel::query()->find($id->value);
        return $row instanceof WebhookDeliveryModel ? $this->mapper->toDomain($row) : null;
    }

    public function findDueForDispatch(DateTimeImmutable $now, int $limit): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, WebhookDeliveryModel> $rows */
        $rows = WebhookDeliveryModel::query()
            ->where('status', WebhookDeliveryStatus::Pending->value)
            ->where('scheduled_at', '<=', $now)
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        return array_values(array_map(
            fn (WebhookDeliveryModel $r) => $this->mapper->toDomain($r),
            $rows->all(),
        ));
    }

    public function save(WebhookDelivery $delivery): void
    {
        $row = $this->mapper->toRow($delivery);
        WebhookDeliveryModel::query()->updateOrInsert(['id' => $delivery->id->value], $row);
    }
}
