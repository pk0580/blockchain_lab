<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Webhook\Domain\Entity\WebhookSubscription;
use App\Modules\Webhook\Domain\Repository\WebhookSubscriptionRepository;
use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use App\Modules\Webhook\Domain\ValueObject\WebhookSubscriptionId;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Mappers\WebhookSubscriptionMapper;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Models\WebhookSubscriptionModel;

final readonly class EloquentWebhookSubscriptionRepository implements WebhookSubscriptionRepository
{
    public function __construct(private WebhookSubscriptionMapper $mapper) {}

    public function findById(WebhookSubscriptionId $id): ?WebhookSubscription
    {
        $row = WebhookSubscriptionModel::query()->find($id->value);
        return $row instanceof WebhookSubscriptionModel ? $this->mapper->toDomain($row) : null;
    }

    public function findActiveForEvent(WebhookEventName $event): array
    {
        // Postgres jsonb-contains: WHERE events @> '"event.name"'. Использую whereJsonContains
        // — Laravel сам подставляет правильный оператор.
        /** @var \Illuminate\Database\Eloquent\Collection<int, WebhookSubscriptionModel> $rows */
        $rows = WebhookSubscriptionModel::query()
            ->where('active', true)
            ->whereJsonContains('events', $event->value)
            ->get();

        return array_values(array_map(
            fn (WebhookSubscriptionModel $r) => $this->mapper->toDomain($r),
            $rows->all(),
        ));
    }

    public function save(WebhookSubscription $subscription): void
    {
        $row = $this->mapper->toRow($subscription);
        WebhookSubscriptionModel::query()->updateOrInsert(
            ['id' => $subscription->id->value],
            $row,
        );
    }
}
