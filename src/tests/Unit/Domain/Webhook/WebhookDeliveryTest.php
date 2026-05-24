<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Webhook;

use App\Modules\Webhook\Domain\Entity\WebhookDelivery;
use App\Modules\Webhook\Domain\Event\WebhookDelivered;
use App\Modules\Webhook\Domain\Event\WebhookDeliveryFailed;
use App\Modules\Webhook\Domain\Exception\InvalidDeliveryStateTransitionException;
use App\Modules\Webhook\Domain\ValueObject\OutboxMessageId;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryId;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryStatus;
use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use App\Modules\Webhook\Domain\ValueObject\WebhookSubscriptionId;
use DateTimeImmutable;

function makeDelivery(): WebhookDelivery
{
    return WebhookDelivery::schedule(
        id: new WebhookDeliveryId('11111111-1111-4111-8111-111111111111'),
        outboxId: new OutboxMessageId('22222222-2222-4222-8222-222222222222'),
        subscriptionId: new WebhookSubscriptionId('33333333-3333-4333-8333-333333333333'),
        eventName: new WebhookEventName('withdrawal.confirmed'),
        payload: ['hello' => 'world'],
        now: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    );
}

it('starts in Pending with attempt=0', function (): void {
    $d = makeDelivery();
    expect($d->status())->toBe(WebhookDeliveryStatus::Pending);
    expect($d->attempts())->toBe(0);
});

it('markDelivered emits WebhookDelivered and increments attempts', function (): void {
    $d = makeDelivery();
    $d->markDelivered(202, new DateTimeImmutable());

    expect($d->status())->toBe(WebhookDeliveryStatus::Delivered);
    expect($d->attempts())->toBe(1);
    expect($d->lastResponseStatus())->toBe(202);
    expect($d->deliveredAt())->not->toBeNull();

    $events = $d->pullPendingEvents();
    expect($events[0])->toBeInstanceOf(WebhookDelivered::class);
});

it('markFailed emits WebhookDeliveryFailed with reason', function (): void {
    $d = makeDelivery();
    $d->markFailed('http 404', 404, new DateTimeImmutable());

    expect($d->status())->toBe(WebhookDeliveryStatus::Failed);
    expect($d->attempts())->toBe(1);
    expect($d->lastResponseStatus())->toBe(404);

    /** @var WebhookDeliveryFailed $evt */
    $evt = $d->pullPendingEvents()[0];
    expect($evt)->toBeInstanceOf(WebhookDeliveryFailed::class);
    expect($evt->reason)->toBe('http 404');
});

it('reschedule keeps Pending, increments attempts, moves scheduledAt', function (): void {
    $d = makeDelivery();
    $next = new DateTimeImmutable('2026-01-01T00:30:00Z');
    $d->reschedule('http 503', 503, $next, new DateTimeImmutable());

    expect($d->status())->toBe(WebhookDeliveryStatus::Pending);
    expect($d->attempts())->toBe(1);
    expect($d->scheduledAt()->format(DATE_ATOM))->toBe($next->format(DATE_ATOM));
    expect($d->pullPendingEvents())->toBe([]);
});

it('forbids markDelivered with non-2xx', function (): void {
    $d = makeDelivery();
    expect(fn () => $d->markDelivered(500, new DateTimeImmutable()))
        ->toThrow(\InvalidArgumentException::class);
});

it('forbids re-transition from Delivered to Pending', function (): void {
    $d = makeDelivery();
    $d->markDelivered(200, new DateTimeImmutable());
    expect(fn () => $d->reschedule('x', 500, new DateTimeImmutable(), new DateTimeImmutable()))
        ->toThrow(InvalidDeliveryStateTransitionException::class);
});
