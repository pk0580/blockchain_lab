<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Application\UseCase\PublishOutbox;

final readonly class PublishOutboxResult
{
    /**
     * @param list<string> $publishedIds
     * @param list<string> $deliveryIds
     */
    public function __construct(
        public array $publishedIds,
        public array $deliveryIds,
    ) {}
}
