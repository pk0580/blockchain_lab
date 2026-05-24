<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Support;

use App\Modules\Webhook\Application\Contract\WebhookEventDispatcher;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelEventDispatcher implements WebhookEventDispatcher
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function dispatch(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
