<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Infrastructure\Provider;

use App\Modules\Confirmation\Domain\Event\TransactionConfirmed;
use App\Modules\ReorgDetection\Domain\Event\ReorgDetected;
use App\Modules\Webhook\Application\Contract\IdGenerator;
use App\Modules\Webhook\Application\Contract\WebhookEventDispatcher;
use App\Modules\Webhook\Domain\Contract\WebhookDispatcher;
use App\Modules\Webhook\Domain\Repository\OutboxRepository;
use App\Modules\Webhook\Domain\Repository\WebhookDeliveryRepository;
use App\Modules\Webhook\Domain\Repository\WebhookSubscriptionRepository;
use App\Modules\Webhook\Infrastructure\Http\HttpWebhookDispatcher;
use App\Modules\Webhook\Infrastructure\Listener\RecordOutboxOnReorgDetected;
use App\Modules\Webhook\Infrastructure\Listener\RecordOutboxOnTransactionConfirmed;
use App\Modules\Webhook\Infrastructure\Listener\RecordOutboxOnWithdrawalConfirmed;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Mappers\OutboxMessageMapper;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Mappers\WebhookDeliveryMapper;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Mappers\WebhookSubscriptionMapper;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Repositories\EloquentOutboxRepository;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Repositories\EloquentWebhookDeliveryRepository;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Repositories\EloquentWebhookSubscriptionRepository;
use App\Modules\Webhook\Infrastructure\Support\LaravelEventDispatcher;
use App\Modules\Webhook\Infrastructure\Support\UuidIdGenerator;
use App\Modules\Withdrawal\Domain\Event\WithdrawalConfirmed;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class WebhookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OutboxRepository::class, function (): OutboxRepository {
            return new EloquentOutboxRepository(new OutboxMessageMapper());
        });

        $this->app->singleton(WebhookDeliveryRepository::class, function (): WebhookDeliveryRepository {
            return new EloquentWebhookDeliveryRepository(new WebhookDeliveryMapper());
        });

        $this->app->singleton(WebhookSubscriptionRepository::class, function ($app): WebhookSubscriptionRepository {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');
            $allowInsecure = (bool) $config->get('webhook.allow_insecure_urls', false);
            return new EloquentWebhookSubscriptionRepository(
                new WebhookSubscriptionMapper(allowInsecureUrls: $allowInsecure),
            );
        });

        $this->app->singleton(WebhookDispatcher::class, function ($app): WebhookDispatcher {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');
            return new HttpWebhookDispatcher(
                http: $app->make(HttpFactory::class),
                timeoutSeconds: (int) $config->get('webhook.http.timeout_seconds', 5),
                connectTimeoutSeconds: (int) $config->get('webhook.http.connect_timeout_seconds', 3),
            );
        });

        $this->app->singleton(IdGenerator::class, UuidIdGenerator::class);
        $this->app->singleton(WebhookEventDispatcher::class, LaravelEventDispatcher::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Persistence/migrations');

        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);
        $events->listen(WithdrawalConfirmed::class, RecordOutboxOnWithdrawalConfirmed::class);
        $events->listen(TransactionConfirmed::class, RecordOutboxOnTransactionConfirmed::class);
        $events->listen(ReorgDetected::class, RecordOutboxOnReorgDetected::class);
    }
}
