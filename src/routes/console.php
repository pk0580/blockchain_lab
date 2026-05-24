<?php

use App\Modules\Idempotency\Infrastructure\Job\CleanupExpiredIdempotencyKeysJob;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\NodeHealth\Infrastructure\Job\ProbeChainEndpointsJob;
use App\Modules\Webhook\Infrastructure\Job\DispatchDueDeliveriesJob;
use App\Modules\Webhook\Infrastructure\Job\PublishOutboxJob;
use App\Modules\Withdrawal\Infrastructure\Job\WatchStuckWithdrawalsJob;
use App\Modules\Withdrawal\Infrastructure\Job\WatchWithdrawalConfirmationsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Вывести вдохновляющую цитату');

/*
|--------------------------------------------------------------------------
| Phase 6.3 — per-chain polling
|--------------------------------------------------------------------------
| Каждые 30 секунд диспатчим `WatchWithdrawalConfirmationsJob` на per-chain
| очередь `confirmations.{chain}`. Каждые 60 секунд — `WatchStuckWithdrawalsJob`
| на `withdrawals.{chain}`. Per-chain очереди дают bulkhead-изоляцию: проблема
| на одной сети не задерживает остальные.
*/
Schedule::call(function (): void {
    /** @var ChainRepository $chains */
    $chains = app(ChainRepository::class);
    /** @var int $batch */
    $batch = (int) config('withdrawal.polling.confirmations_batch_size', 100);

    foreach ($chains->allEnabled() as $chain) {
        WatchWithdrawalConfirmationsJob::dispatch($chain->id->value, $batch);
    }
})->everyThirtySeconds()
    ->name('withdrawal.watch_confirmations')
    ->withoutOverlapping(5);

Schedule::call(function (): void {
    /** @var ChainRepository $chains */
    $chains = app(ChainRepository::class);

    foreach ($chains->allEnabled() as $chain) {
        WatchStuckWithdrawalsJob::dispatch($chain->id->value);
    }
})->everyMinute()
    ->name('withdrawal.watch_stuck')
    ->withoutOverlapping(5);

/*
|--------------------------------------------------------------------------
| Phase 7.1 — NodeHealth probe
|--------------------------------------------------------------------------
| Per-chain probe каждые 30 секунд. ProbeChainEndpointsJob ставится на очередь
| `health.{chain_id}` чтобы провисший endpoint одной chain не задерживал probing
| остальных сетей.
*/
Schedule::call(function (): void {
    /** @var ChainRepository $chains */
    $chains = app(ChainRepository::class);
    foreach ($chains->allEnabled() as $chain) {
        ProbeChainEndpointsJob::dispatch($chain->id->value);
    }
})->everyThirtySeconds()
    ->name('node_health.probe')
    ->withoutOverlapping(5);

/*
|--------------------------------------------------------------------------
| Phase 7.2 — Webhook через Outbox pattern
|--------------------------------------------------------------------------
| - PublishOutboxJob (every minute): берёт unpublished outbox messages, делает
|   fan-out в matching subscriptions, создаёт WebhookDeliveries.
| - DispatchDueDeliveriesJob (every minute): забирает Pending deliveries с
|   scheduledAt <= now и пытается их доставить с HMAC-подписью.
*/
Schedule::job(new PublishOutboxJob())
    ->everyMinute()
    ->name('webhook.publish_outbox')
    ->withoutOverlapping(5);

Schedule::job(new DispatchDueDeliveriesJob())
    ->everyMinute()
    ->name('webhook.dispatch_due')
    ->withoutOverlapping(5);

/*
|--------------------------------------------------------------------------
| Phase 7.3 — Idempotency cleanup
|--------------------------------------------------------------------------
| Раз в сутки удаляем просроченные записи (expires_at <= now). TTL по умолчанию
| 24h, поэтому daily cleanup гарантирует ограниченный объём таблицы.
*/
Schedule::job(new CleanupExpiredIdempotencyKeysJob())
    ->daily()
    ->name('idempotency.cleanup_expired')
    ->withoutOverlapping(5);
