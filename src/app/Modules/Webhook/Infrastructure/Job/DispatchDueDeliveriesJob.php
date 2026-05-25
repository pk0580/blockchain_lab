<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Job;

use App\Modules\Webhook\Application\UseCase\DispatchWebhookDelivery\DispatchWebhookDeliveryAction;
use App\Modules\Webhook\Application\UseCase\DispatchWebhookDelivery\DispatchWebhookDeliveryData;
use App\Modules\Webhook\Domain\Repository\WebhookDeliveryRepository;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Реальная отправка HTTP-вебхуков (GUIDE.md, Урок 12.2, шаг 3 + Урок 12.6).
 *
 * Каждую минуту: берём Pending deliveries с scheduledAt ≤ now → дёргаем
 * {@see DispatchWebhookDeliveryAction}, который через {@see HttpWebhookDispatcher}
 * шлёт POST с HMAC-подписью. Сбой одной delivery не валит всю партию.
 *
 * Очередь — `webhooks`, изолированная от ingestion и подтверждений (bulkhead).
 *
 * @see \GUIDE.md  Урок 12 (#урок-12--надёжность-и-наблюдаемость)
 */
final class DispatchDueDeliveriesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $limit = 100)
    {
        $this->onQueue('webhooks');
    }

    public function handle(
        WebhookDeliveryRepository $repo,
        DispatchWebhookDeliveryAction $action,
    ): void {
        $now = new DateTimeImmutable();
        $rows = $repo->findDueForDispatch($now, $this->limit);

        foreach ($rows as $delivery) {
            try {
                $action->handle(new DispatchWebhookDeliveryData($delivery->id->value));
            } catch (Throwable $e) {
                Log::warning('webhook.dispatch.failed', [
                    'delivery_id' => $delivery->id->value,
                    'reason' => $e->getMessage(),
                ]);
            }
        }
    }
}
