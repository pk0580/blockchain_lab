<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Application\UseCase\PublishOutbox;

final readonly class PublishOutboxData
{
    public function __construct(public int $limit = 100) {}
}
