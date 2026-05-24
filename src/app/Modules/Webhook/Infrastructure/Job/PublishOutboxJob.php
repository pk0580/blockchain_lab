<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Job;

use App\Modules\Webhook\Application\UseCase\PublishOutbox\PublishOutboxAction;
use App\Modules\Webhook\Application\UseCase\PublishOutbox\PublishOutboxData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Каждую минуту: берём unpublished outbox и fan-out'им в deliveries.
 * Очередь `outbox` (отдельная, чтобы fan-out не блокировал per-chain workers).
 */
final class PublishOutboxJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $limit = 100)
    {
        $this->onQueue('outbox');
    }

    public function handle(PublishOutboxAction $action): void
    {
        $action->handle(new PublishOutboxData($this->limit));
    }
}
