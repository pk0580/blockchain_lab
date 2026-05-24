<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Application\Contract;

interface WebhookEventDispatcher
{
    public function dispatch(object $event): void;
}
