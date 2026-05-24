<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Domain\Contract;

use App\Modules\Webhook\Domain\Entity\WebhookDelivery;
use App\Modules\Webhook\Domain\Entity\WebhookSubscription;
use App\Modules\Webhook\Domain\ValueObject\WebhookDispatchOutcome;

/**
 * Узкий порт: «доставь HTTP POST на subscription с HMAC-подписью».
 * Реализация (`Infrastructure\Http\HttpWebhookDispatcher`) формирует тело,
 * подписывает, отправляет с timeout'ом, возвращает outcome для Application'а.
 *
 * Никогда не throw'ит сетевые ошибки — они оборачиваются в outcome.success=false.
 */
interface WebhookDispatcher
{
    public function dispatch(
        WebhookSubscription $subscription,
        WebhookDelivery $delivery,
    ): WebhookDispatchOutcome;
}
