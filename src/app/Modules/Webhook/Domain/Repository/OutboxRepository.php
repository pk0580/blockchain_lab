<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\Repository;

use App\Modules\Webhook\Domain\Entity\OutboxMessage;
use App\Modules\Webhook\Domain\ValueObject\OutboxMessageId;

interface OutboxRepository
{
    public function findById(OutboxMessageId $id): ?OutboxMessage;

    /**
     * Unpublished записи в порядке createdAt ASC. Используется PublishOutboxAction
     * который должен обработать «первое-в-очередь» (стабильный порядок per-aggregate).
     *
     * @return list<OutboxMessage>
     */
    public function findUnpublished(int $limit): array;

    public function save(OutboxMessage $message): void;
}
