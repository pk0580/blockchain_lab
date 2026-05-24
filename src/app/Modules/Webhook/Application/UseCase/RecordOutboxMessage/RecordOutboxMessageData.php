<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Application\UseCase\RecordOutboxMessage;

final readonly class RecordOutboxMessageData
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $eventName,
        public string $aggregateId,
        public array $payload,
    ) {}
}
