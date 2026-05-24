<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Mappers;

use App\Modules\Webhook\Domain\Entity\WebhookSubscription;
use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use App\Modules\Webhook\Domain\ValueObject\WebhookSecret;
use App\Modules\Webhook\Domain\ValueObject\WebhookSubscriptionId;
use App\Modules\Webhook\Domain\ValueObject\WebhookUrl;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Models\WebhookSubscriptionModel;
use DateTimeImmutable;

final class WebhookSubscriptionMapper
{
    public function __construct(private readonly bool $allowInsecureUrls = false) {}

    public function toDomain(WebhookSubscriptionModel $row): WebhookSubscription
    {
        $events = is_array($row->events)
            ? $row->events
            : (array) json_decode((string) $row->events, true);

        $eventVOs = [];
        foreach ($events as $name) {
            if (is_string($name) && $name !== '') {
                $eventVOs[] = new WebhookEventName($name);
            }
        }

        return new WebhookSubscription(
            id: new WebhookSubscriptionId($row->id),
            url: new WebhookUrl($row->url, $this->allowInsecureUrls),
            secret: new WebhookSecret($row->secret),
            events: $eventVOs,
            active: $row->active,
            createdAt: DateTimeImmutable::createFromInterface($row->created_at),
        );
    }

    /**
     * @return array{
     *     id: string, url: string, secret: string, events: string,
     *     active: bool, created_at: \DateTimeImmutable,
     * }
     */
    public function toRow(WebhookSubscription $sub): array
    {
        $names = [];
        foreach ($sub->events as $e) {
            $names[] = $e->value;
        }

        return [
            'id' => $sub->id->value,
            'url' => $sub->url->value,
            'secret' => $sub->secret->value,
            'events' => (string) json_encode($names, JSON_THROW_ON_ERROR),
            'active' => $sub->active,
            'created_at' => $sub->createdAt,
        ];
    }
}
