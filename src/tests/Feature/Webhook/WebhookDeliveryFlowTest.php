<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use App\Modules\Webhook\Application\UseCase\DispatchWebhookDelivery\DispatchWebhookDeliveryAction;
use App\Modules\Webhook\Application\UseCase\DispatchWebhookDelivery\DispatchWebhookDeliveryData;
use App\Modules\Webhook\Application\UseCase\PublishOutbox\PublishOutboxAction;
use App\Modules\Webhook\Application\UseCase\PublishOutbox\PublishOutboxData;
use App\Modules\Webhook\Application\UseCase\RecordOutboxMessage\RecordOutboxMessageAction;
use App\Modules\Webhook\Application\UseCase\RecordOutboxMessage\RecordOutboxMessageData;
use App\Modules\Webhook\Domain\Entity\WebhookSubscription;
use App\Modules\Webhook\Domain\Repository\WebhookDeliveryRepository;
use App\Modules\Webhook\Domain\Repository\WebhookSubscriptionRepository;
use App\Modules\Webhook\Domain\ValueObject\WebhookDeliveryStatus;
use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use App\Modules\Webhook\Domain\ValueObject\WebhookSecret;
use App\Modules\Webhook\Domain\ValueObject\WebhookSignature;
use App\Modules\Webhook\Domain\ValueObject\WebhookSubscriptionId;
use App\Modules\Webhook\Domain\ValueObject\WebhookUrl;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Models\OutboxMessageModel;
use App\Modules\Webhook\Infrastructure\Persistence\Eloquent\Models\WebhookDeliveryModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeSubscription(string $idSuffix, string $event, string $url, string $secret): WebhookSubscription
{
    return new WebhookSubscription(
        id: new WebhookSubscriptionId('aaaaaaaa-aaaa-4aaa-8aaa-'.str_pad($idSuffix, 12, 'a', STR_PAD_LEFT)),
        url: new WebhookUrl($url),
        secret: new WebhookSecret($secret),
        events: [new WebhookEventName($event)],
        active: true,
        createdAt: new DateTimeImmutable(),
    );
}

it('records outbox → publishes fan-out → dispatches with HMAC headers', function (): void {
    $secret = str_repeat('s', 64);
    $sub = makeSubscription(
        '1',
        'withdrawal.confirmed',
        'https://hook.example.com/listener',
        $secret,
    );
    /** @var WebhookSubscriptionRepository $subs */
    $subs = app(WebhookSubscriptionRepository::class);
    $subs->save($sub);

    /** @var RecordOutboxMessageAction $record */
    $record = app(RecordOutboxMessageAction::class);
    $record->handle(new RecordOutboxMessageData(
        eventName: 'withdrawal.confirmed',
        aggregateId: 'wd-1',
        payload: ['withdrawal_id' => 'wd-1', 'confirmations' => 3],
    ));

    expect(OutboxMessageModel::query()->count())->toBe(1);

    /** @var PublishOutboxAction $publish */
    $publish = app(PublishOutboxAction::class);
    $publish->handle(new PublishOutboxData(50));

    expect(WebhookDeliveryModel::query()->count())->toBe(1);
    expect(OutboxMessageModel::query()->whereNotNull('published_at')->count())->toBe(1);

    Http::fake([
        'hook.example.com/*' => Http::response('ok', 202),
    ]);

    /** @var WebhookDeliveryRepository $deliveries */
    $deliveries = app(WebhookDeliveryRepository::class);
    $due = $deliveries->findDueForDispatch(new DateTimeImmutable(), 10);
    expect($due)->toHaveCount(1);

    /** @var DispatchWebhookDeliveryAction $dispatch */
    $dispatch = app(DispatchWebhookDeliveryAction::class);
    $result = $dispatch->handle(new DispatchWebhookDeliveryData($due[0]->id->value));

    expect($result->status)->toBe(WebhookDeliveryStatus::Delivered);
    expect($result->attempts)->toBe(1);
    expect($result->responseStatus)->toBe(202);

    Http::assertSent(function ($request) use ($secret) {
        // Headers присутствуют.
        $sig = $request->header('X-Signature');
        $ts = $request->header('X-Timestamp');
        $event = $request->header('X-Webhook-Event');
        if ($sig === [] || $ts === [] || $event === []) {
            return false;
        }
        if ($event[0] !== 'withdrawal.confirmed') {
            return false;
        }
        // Signature валидна для (timestamp, body).
        $verified = (new WebhookSignature($sig[0]))->verify(
            new WebhookSecret($secret),
            (int) $ts[0],
            $request->body(),
        );
        return $verified;
    });
});

it('moves to Pending with bumped attempts on 5xx (retryable)', function (): void {
    $sub = makeSubscription(
        '2',
        'withdrawal.confirmed',
        'https://retry.example.com/hook',
        str_repeat('t', 64),
    );
    /** @var WebhookSubscriptionRepository $subs */
    $subs = app(WebhookSubscriptionRepository::class);
    $subs->save($sub);

    /** @var RecordOutboxMessageAction $record */
    $record = app(RecordOutboxMessageAction::class);
    $record->handle(new RecordOutboxMessageData(
        eventName: 'withdrawal.confirmed',
        aggregateId: 'wd-2',
        payload: ['x' => 1],
    ));
    app(PublishOutboxAction::class)->handle(new PublishOutboxData(10));

    Http::fake([
        'retry.example.com/*' => Http::response('boom', 503),
    ]);

    /** @var WebhookDeliveryRepository $deliveries */
    $deliveries = app(WebhookDeliveryRepository::class);
    $delivery = $deliveries->findDueForDispatch(new DateTimeImmutable(), 10)[0];

    $result = app(DispatchWebhookDeliveryAction::class)->handle(
        new DispatchWebhookDeliveryData($delivery->id->value),
    );

    expect($result->status)->toBe(WebhookDeliveryStatus::Pending);
    expect($result->attempts)->toBe(1);
    expect($result->rescheduled)->toBeTrue();
    expect($result->responseStatus)->toBe(503);
});

it('moves to Failed on 4xx (non-retryable)', function (): void {
    $sub = makeSubscription(
        '3',
        'withdrawal.confirmed',
        'https://forbidden.example.com/hook',
        str_repeat('u', 64),
    );
    app(WebhookSubscriptionRepository::class)->save($sub);

    app(RecordOutboxMessageAction::class)->handle(new RecordOutboxMessageData(
        eventName: 'withdrawal.confirmed',
        aggregateId: 'wd-3',
        payload: ['x' => 1],
    ));
    app(PublishOutboxAction::class)->handle(new PublishOutboxData(10));

    Http::fake([
        'forbidden.example.com/*' => Http::response('no', 401),
    ]);

    $delivery = app(WebhookDeliveryRepository::class)
        ->findDueForDispatch(new DateTimeImmutable(), 10)[0];

    $result = app(DispatchWebhookDeliveryAction::class)->handle(
        new DispatchWebhookDeliveryData($delivery->id->value),
    );

    expect($result->status)->toBe(WebhookDeliveryStatus::Failed);
    expect($result->responseStatus)->toBe(401);
});

it('marks as Failed when retryable but max_attempts reached', function (): void {
    config(['webhook.retry.max_attempts' => 1]);

    $sub = makeSubscription(
        '4',
        'withdrawal.confirmed',
        'https://exhaust.example.com/hook',
        str_repeat('v', 64),
    );
    app(WebhookSubscriptionRepository::class)->save($sub);

    app(RecordOutboxMessageAction::class)->handle(new RecordOutboxMessageData(
        eventName: 'withdrawal.confirmed',
        aggregateId: 'wd-4',
        payload: ['x' => 1],
    ));
    app(PublishOutboxAction::class)->handle(new PublishOutboxData(10));

    Http::fake([
        'exhaust.example.com/*' => Http::response('boom', 500),
    ]);

    $delivery = app(WebhookDeliveryRepository::class)
        ->findDueForDispatch(new DateTimeImmutable(), 10)[0];

    $result = app(DispatchWebhookDeliveryAction::class)->handle(
        new DispatchWebhookDeliveryData($delivery->id->value),
    );

    // attempts будет 1 после попытки, max=1 → нельзя retry → Failed.
    expect($result->status)->toBe(WebhookDeliveryStatus::Failed);
});

it('does not create deliveries for non-matching subscriptions', function (): void {
    // Подписка на reorg.detected, но event = withdrawal.confirmed → 0 deliveries.
    $sub = makeSubscription(
        '5',
        'reorg.detected',
        'https://other.example.com/hook',
        str_repeat('w', 64),
    );
    app(WebhookSubscriptionRepository::class)->save($sub);

    app(RecordOutboxMessageAction::class)->handle(new RecordOutboxMessageData(
        eventName: 'withdrawal.confirmed',
        aggregateId: 'wd-5',
        payload: [],
    ));
    app(PublishOutboxAction::class)->handle(new PublishOutboxData(10));

    expect(WebhookDeliveryModel::query()->count())->toBe(0);
    // outbox помечается published даже без получателей — alternative было бы
    // зацикливание; current поведение: deliver-or-skip
    expect(OutboxMessageModel::query()->whereNotNull('published_at')->count())->toBe(1);

    // Suppress unused-import warning.
    Str::uuid();
});
