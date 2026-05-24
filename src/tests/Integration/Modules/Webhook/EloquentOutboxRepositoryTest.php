<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Webhook;

use App\Modules\Webhook\Domain\Entity\OutboxMessage;
use App\Modules\Webhook\Domain\Repository\OutboxRepository;
use App\Modules\Webhook\Domain\ValueObject\OutboxMessageId;
use App\Modules\Webhook\Domain\ValueObject\WebhookEventName;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeMessage(string $idSuffix, string $event = 'withdrawal.confirmed'): OutboxMessage
{
    return OutboxMessage::record(
        id: new OutboxMessageId('00000000-0000-4000-8000-'.str_pad($idSuffix, 12, '0', STR_PAD_LEFT)),
        eventName: new WebhookEventName($event),
        aggregateId: 'aggregate-'.$idSuffix,
        payload: ['key' => 'val-'.$idSuffix],
        now: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    );
}

it('round-trips an outbox message', function (): void {
    /** @var OutboxRepository $repo */
    $repo = app(OutboxRepository::class);
    $msg = makeMessage('1');
    $repo->save($msg);

    $loaded = $repo->findById($msg->id);
    expect($loaded)->not->toBeNull();
    expect($loaded?->eventName->value)->toBe('withdrawal.confirmed');
    expect($loaded?->payload)->toBe(['key' => 'val-1']);
    expect($loaded?->isPublished())->toBeFalse();
});

it('findUnpublished orders by created_at ASC and excludes published', function (): void {
    /** @var OutboxRepository $repo */
    $repo = app(OutboxRepository::class);
    $repo->save(makeMessage('a'));
    $repo->save(makeMessage('b'));

    $publishedMsg = makeMessage('c');
    $publishedMsg->markPublished(new DateTimeImmutable());
    $repo->save($publishedMsg);

    $rows = $repo->findUnpublished(10);
    expect($rows)->toHaveCount(2);
});

it('persists markPublished state', function (): void {
    /** @var OutboxRepository $repo */
    $repo = app(OutboxRepository::class);
    $msg = makeMessage('d');
    $repo->save($msg);

    $reloaded = $repo->findById($msg->id);
    expect($reloaded?->isPublished())->toBeFalse();
    $reloaded?->markPublished(new DateTimeImmutable('2026-01-01T01:00:00Z'));
    $repo->save($reloaded);

    $final = $repo->findById($msg->id);
    expect($final?->isPublished())->toBeTrue();
    expect($final?->publishedAt()?->format(DATE_ATOM))->toBe('2026-01-01T01:00:00+00:00');
});
