<?php

declare(strict_types=1);

namespace App\Modules\NodeHealth\Application\Contract;

/**
 * Узкий port над Laravel-диспетчером — Application не знает про Illuminate.
 * Реализация (`Infrastructure\Support\LaravelEventDispatcher`) делегирует.
 */
interface NodeHealthEventDispatcher
{
    public function dispatch(object $event): void;
}
