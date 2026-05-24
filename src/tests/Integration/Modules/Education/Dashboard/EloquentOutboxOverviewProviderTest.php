<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Education\Dashboard;

use App\Modules\Education\Application\Contract\Dashboard\OutboxOverviewProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function insertOutbox(array $overrides = []): string
{
    $id = (string) Str::uuid();
    DB::table('outbox_messages')->insert(array_merge([
        'id' => $id,
        'event_name' => 'withdrawal.confirmed',
        'aggregate_id' => 'agg-'.$id,
        'payload' => json_encode(['ok' => true]),
        'created_at' => now(),
        'published_at' => null,
        'attempts' => 0,
        'last_error' => null,
    ], $overrides));
    return $id;
}

function insertDelivery(string $outboxId, string $status): void
{
    DB::table('webhook_deliveries')->insert([
        'id' => (string) Str::uuid(),
        'outbox_id' => $outboxId,
        'subscription_id' => (string) Str::uuid(),
        'event_name' => 'withdrawal.confirmed',
        'payload' => json_encode(['ok' => true]),
        'status' => $status,
        'attempts' => 0,
        'last_error' => null,
        'last_response_status' => null,
        'created_at' => now(),
        'scheduled_at' => now(),
        'delivered_at' => $status === 'delivered' ? now() : null,
    ]);
}

it('reports zeros on empty tables', function (): void {
    /** @var OutboxOverviewProvider $provider */
    $provider = app(OutboxOverviewProvider::class);
    $section = $provider->load();

    expect($section->unpublishedMessages)->toBe(0);
    expect($section->oldestUnpublishedAt)->toBeNull();
    expect($section->deliveriesPending)->toBe(0);
    expect($section->deliveriesFailed)->toBe(0);
    expect($section->deliveriesDelivered)->toBe(0);
});

it('counts unpublished messages and locates the oldest one', function (): void {
    $oldest = insertOutbox(['created_at' => now()->subHours(2)]);
    insertOutbox(['created_at' => now()->subMinutes(5)]);
    insertOutbox(['created_at' => now(), 'published_at' => now()]);    // already published

    /** @var OutboxOverviewProvider $provider */
    $provider = app(OutboxOverviewProvider::class);
    $section = $provider->load();

    expect($section->unpublishedMessages)->toBe(2);
    expect($section->oldestUnpublishedAt)->not->toBeNull();

    // query builder отдает raw string из БД (timestamptz); парсим тем же
    // способом, что и production-код.
    /** @var string $expectedCreatedAt */
    $expectedCreatedAt = DB::table('outbox_messages')
        ->where('id', $oldest)
        ->value('created_at');
    $expected = CarbonImmutable::parse($expectedCreatedAt)->format(DATE_ATOM);
    expect($section->oldestUnpublishedAt)->toBe($expected);
});

it('breaks down deliveries by status', function (): void {
    $outboxId = insertOutbox();
    insertDelivery($outboxId, 'pending');
    insertDelivery($outboxId, 'pending');
    insertDelivery($outboxId, 'delivered');
    insertDelivery($outboxId, 'failed');

    /** @var OutboxOverviewProvider $provider */
    $provider = app(OutboxOverviewProvider::class);
    $section = $provider->load();

    expect($section->deliveriesPending)->toBe(2);
    expect($section->deliveriesDelivered)->toBe(1);
    expect($section->deliveriesFailed)->toBe(1);
});
