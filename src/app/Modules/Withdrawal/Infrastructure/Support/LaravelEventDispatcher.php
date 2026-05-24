<?php

declare(strict_types=1);

namespace App\Modules\Withdrawal\Infrastructure\Support;

use App\Modules\Withdrawal\Application\Contract\WithdrawalEventDispatcher;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelEventDispatcher implements WithdrawalEventDispatcher
{
    public function __construct(private Dispatcher $dispatcher) {}

    public function dispatch(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
