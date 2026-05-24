<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Infrastructure\Support;

use App\Modules\NodeHealth\Application\Contract\NodeHealthEventDispatcher;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelEventDispatcher implements NodeHealthEventDispatcher
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function dispatch(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
