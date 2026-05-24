<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Application\UseCase\DispatchWebhookDelivery;

use App\Modules\Webhook\Application\Contract\WebhookEventDispatcher;
use App\Modules\Webhook\Domain\Contract\WebhookDispatcher;
use App\Modules\Webhook\Domain\Exception\WebhookDeliveryNotFoundException;
use App\Modules\Webhook\Domain\Exception\WebhookSubscriptionNotFoundException;
use App\Modules\Webhook\Domain\Repository\WebhookDeliveryRepository;
use App\Modules\Webhook\Domain\Repository\WebhookSubscriptionRepository;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryId;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;

/**
 * Делает одну попытку доставки конкретного `WebhookDelivery`:
 *
 *  1. Загружает delivery + subscription.
 *  2. WebhookDispatcher.dispatch → WebhookDispatchOutcome.
 *  3. По outcome решает: markDelivered / markFailed / reschedule.
 *  4. reschedule использует exponential backoff: 30s, 2min, 10min, 1h (всё в
 *     `webhook.retry.backoff_seconds` config). Превышен `webhook.retry.max_attempts`
 *     → markFailed.
 *  5. Эмитит WebhookDelivered / WebhookDeliveryFailed события.
 *
 * Один retry — одно срабатывание action'а: action не sleep'ит и не loop'ит,
 * перепланировку делает `DispatchDueDeliveriesJob`, который запускается раз в
 * минуту и забирает Pending deliveries с `scheduledAt <= now`.
 */
final readonly class DispatchWebhookDeliveryAction
{
    public function __construct(
        private WebhookDeliveryRepository $deliveries,
        private WebhookSubscriptionRepository $subscriptions,
        private WebhookDispatcher $dispatcher,
        private WebhookEventDispatcher $events,
        private ConfigRepository $config,
        private DatabaseManager $db,
    ) {}

    public function handle(DispatchWebhookDeliveryData $data): DispatchWebhookDeliveryResult
    {
        $deliveryId = new WebhookDeliveryId($data->deliveryId);
        $delivery = $this->deliveries->findById($deliveryId)
            ?? throw WebhookDeliveryNotFoundException::byId($deliveryId);

        $subscription = $this->subscriptions->findById($delivery->subscriptionId)
            ?? throw WebhookSubscriptionNotFoundException::byId($delivery->subscriptionId);

        $outcome = $this->dispatcher->dispatch($subscription, $delivery);
        $now = new DateTimeImmutable();

        $maxAttempts = (int) $this->config->get('webhook.retry.max_attempts', 5);
        /** @var list<int> $backoff */
        $backoff = (array) $this->config->get('webhook.retry.backoff_seconds', [30, 120, 600, 3600]);

        $rescheduled = false;
        $events = [];

        $this->db->transaction(function () use (
            $delivery,
            $outcome,
            $now,
            $maxAttempts,
            $backoff,
            &$rescheduled,
            &$events,
        ): void {
            if ($outcome->success && $outcome->responseStatus !== null) {
                $delivery->markDelivered($outcome->responseStatus, $now);
            } elseif ($outcome->retryable && $delivery->attempts() + 1 < $maxAttempts) {
                // attempts() будет инкрементировано внутри reschedule.
                $nextAttempt = $delivery->attempts(); // 0-based индекс в backoff после увеличения
                $delaySeconds = $backoff[$nextAttempt] ?? end($backoff);
                $delaySeconds = is_int($delaySeconds) ? $delaySeconds : 60;
                $next = $now->modify("+{$delaySeconds} seconds");
                $delivery->reschedule($outcome->error ?? 'retryable', $outcome->responseStatus, $next, $now);
                $rescheduled = true;
            } else {
                $reason = $outcome->error ?? 'non-retryable response';
                $delivery->markFailed($reason, $outcome->responseStatus, $now);
            }

            $this->deliveries->save($delivery);
            $events = $delivery->pullPendingEvents();
        });

        foreach ($events as $event) {
            $this->events->dispatch($event);
        }

        return new DispatchWebhookDeliveryResult(
            status: $delivery->status(),
            attempts: $delivery->attempts(),
            rescheduled: $rescheduled,
            responseStatus: $outcome->responseStatus,
        );
    }
}
