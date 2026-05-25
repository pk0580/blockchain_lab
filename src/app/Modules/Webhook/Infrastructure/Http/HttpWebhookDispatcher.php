<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Http;

use App\Modules\Webhook\Domain\Contract\WebhookDispatcher;
use App\Modules\Webhook\Domain\Entity\WebhookDelivery;
use App\Modules\Webhook\Domain\Entity\WebhookSubscription;
use App\Modules\Webhook\Domain\ValueObject\WebhookDispatchOutcome;
use App\Modules\Webhook\Domain\ValueObject\WebhookSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * HTTP-доставка webhook'а с HMAC-подписью (GUIDE.md, Урок 12.3).
 *
 * Запрос:
 *   POST <subscription.url>
 *   Content-Type: application/json
 *   X-Webhook-Event:    <event.name>
 *   X-Webhook-Delivery: <delivery.id>
 *   X-Timestamp:        <epoch seconds>
 *   X-Signature:        sha256=<hex>
 *
 * Body — JSON `{"event": ..., "data": <payload>, "delivery_id": ...}`.
 *
 * Решение по статусу ответа (GUIDE §12.3 конец):
 *   - 2xx                  → delivered (успех).
 *   - 4xx                  → failed (постоянная ошибка, ретраить бессмысленно).
 *   - 5xx / timeout / DNS  → retryable (повторим позже).
 *
 * Таймауты — обязательны (GUIDE §12, паттерн Resilience): «нет приемлемого
 * default-а ‘ждать вечно’».
 *
 * @see \GUIDE.md  Урок 12 (#урок-12--надёжность-и-наблюдаемость)
 */
final readonly class HttpWebhookDispatcher implements WebhookDispatcher
{
    public function __construct(
        private HttpFactory $http,
        private int $timeoutSeconds = 5,
        private int $connectTimeoutSeconds = 3,
    ) {}

    public function dispatch(
        WebhookSubscription $subscription,
        WebhookDelivery $delivery,
    ): WebhookDispatchOutcome {
        $timestamp = time();
        $body = (string) json_encode([
            'event' => $delivery->eventName->value,
            'delivery_id' => $delivery->id->value,
            'data' => $delivery->payload,
        ], JSON_THROW_ON_ERROR);

        $signature = WebhookSignature::compute($subscription->secret, $timestamp, $body);

        $start = (int) (microtime(true) * 1000);
        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->connectTimeout($this->connectTimeoutSeconds)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Event' => $delivery->eventName->value,
                    'X-Webhook-Delivery' => $delivery->id->value,
                    'X-Timestamp' => (string) $timestamp,
                    'X-Signature' => $signature->value,
                ])
                ->withBody($body, 'application/json')
                ->post($subscription->url->value);
        } catch (ConnectionException $e) {
            $latency = max(0, ((int) (microtime(true) * 1000)) - $start);
            return WebhookDispatchOutcome::retryable(null, 'connection:'.$e->getMessage(), $latency);
        } catch (Throwable $e) {
            $latency = max(0, ((int) (microtime(true) * 1000)) - $start);
            return WebhookDispatchOutcome::retryable(null, 'transport:'.$e->getMessage(), $latency);
        }

        $latency = max(0, ((int) (microtime(true) * 1000)) - $start);
        $status = $response->status();

        if ($status >= 200 && $status < 300) {
            return WebhookDispatchOutcome::delivered($status, $latency);
        }
        if ($status >= 400 && $status < 500) {
            return WebhookDispatchOutcome::failed($status, "http {$status}", $latency);
        }
        return WebhookDispatchOutcome::retryable($status, "http {$status}", $latency);
    }
}
