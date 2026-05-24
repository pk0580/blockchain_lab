<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Models\OutboxMessageModel;
use App\Modules\Withdrawal\Domain\Event\WithdrawalConfirmed;
use App\Modules\Withdrawal\Domain\ValueObject\WithdrawalId;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records outbox row when WithdrawalConfirmed is dispatched', function (): void {
    /** @var Dispatcher $events */
    $events = app(Dispatcher::class);

    $events->dispatch(new WithdrawalConfirmed(
        id: new WithdrawalId('44444444-4444-4444-8444-444444444444'),
        confirmations: 6,
        occurredAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    ));

    $row = OutboxMessageModel::query()->first();
    expect($row)->not->toBeNull();
    expect($row?->event_name)->toBe('withdrawal.confirmed');
    expect($row?->aggregate_id)->toBe('44444444-4444-4444-8444-444444444444');
    expect($row?->payload)->toMatchArray(['confirmations' => 6]);
});
