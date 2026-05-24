<?php

declare(strict_types=1);

namespace App\Modules\Education\Application\DTO\Dashboard;

/**
 * Lag-индикатор по outbox + webhook pipeline. `oldestUnpublishedAt` = null
 * означает либо «всё опубликовано», либо «нет ни одной записи» — для UI
 * различия нет.
 */
final readonly class OutboxSection
{
    public function __construct(
        public int $unpublishedMessages,
        public ?string $oldestUnpublishedAt,
        public int $deliveriesPending,
        public int $deliveriesFailed,
        public int $deliveriesDelivered,
    ) {}

    /**
     * @return array{unpublished_messages:int, oldest_unpublished_at:?string, deliveries_pending:int, deliveries_failed:int, deliveries_delivered:int}
     */
    public function toArray(): array
    {
        return [
            'unpublished_messages' => $this->unpublishedMessages,
            'oldest_unpublished_at' => $this->oldestUnpublishedAt,
            'deliveries_pending' => $this->deliveriesPending,
            'deliveries_failed' => $this->deliveriesFailed,
            'deliveries_delivered' => $this->deliveriesDelivered,
        ];
    }
}
