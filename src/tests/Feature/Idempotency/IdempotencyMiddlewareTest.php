<?php

declare(strict_types=1);

namespace Tests\Feature\Idempotency;

use App\Modules\Idempotency\Infrastructure\Http\IdempotencyMiddleware;
use App\Modules\Idempotency\Infrastructure\Persistence\Eloquent\IdempotencyKeyModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->hits = new \stdClass();
    $this->hits->count = 0;

    Route::middleware([IdempotencyMiddleware::class])
        ->post('/api/v1/_test/idempotent', function (Request $request): Response {
            $this->hits->count++;
            return new Response(
                json_encode(['data' => ['v' => $request->json('v'), 'hits' => $this->hits->count]]),
                Response::HTTP_CREATED,
                ['Content-Type' => 'application/json'],
            );
        });

    Route::middleware([IdempotencyMiddleware::class])
        ->post('/api/v1/_test/server-error', function (): Response {
            $this->hits->count++;
            return new Response(
                json_encode(['error' => 'boom']),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'application/json'],
            );
        });

    Route::middleware([IdempotencyMiddleware::class])
        ->get('/api/v1/_test/idempotent', function (): Response {
            $this->hits->count++;
            return new Response('"ok"', 200, ['Content-Type' => 'application/json']);
        });
});

it('passes POST without Idempotency-Key straight through', function (): void {
    $response = $this->postJson('/api/v1/_test/idempotent', ['v' => 1]);

    $response->assertCreated()->assertJsonPath('data.v', 1);
    expect($this->hits->count)->toBe(1);
    expect(IdempotencyKeyModel::query()->count())->toBe(0);
});

it('ignores the header on non-POST requests', function (): void {
    $a = $this->withHeader(IdempotencyMiddleware::HEADER, 'idem-get-001')
        ->getJson('/api/v1/_test/idempotent');
    $b = $this->withHeader(IdempotencyMiddleware::HEADER, 'idem-get-001')
        ->getJson('/api/v1/_test/idempotent');

    $a->assertOk();
    $b->assertOk();
    expect($this->hits->count)->toBe(2);
    expect(IdempotencyKeyModel::query()->count())->toBe(0);
});

it('rejects invalid keys with 400 idempotency_key_invalid', function (): void {
    $response = $this->withHeader(IdempotencyMiddleware::HEADER, 'no')
        ->postJson('/api/v1/_test/idempotent', ['v' => 1]);

    $response->assertStatus(Response::HTTP_BAD_REQUEST)
        ->assertJsonPath('error.code', 'idempotency_key_invalid');
    expect($this->hits->count)->toBe(0);
});

it('replays the cached response on a repeat request with the same key and body', function (): void {
    $payload = ['v' => 42];
    $first = $this->withHeader(IdempotencyMiddleware::HEADER, 'idem-replay-1')
        ->postJson('/api/v1/_test/idempotent', $payload);
    $second = $this->withHeader(IdempotencyMiddleware::HEADER, 'idem-replay-1')
        ->postJson('/api/v1/_test/idempotent', $payload);

    $first->assertCreated();
    $second->assertCreated();
    expect($this->hits->count)->toBe(1);
    expect($second->headers->get(IdempotencyMiddleware::REPLAY_HEADER))->toBe('true');
    expect($second->getContent())->toBe($first->getContent());
});

it('returns 409 idempotency_conflict on same key with different body', function (): void {
    $this->withHeader(IdempotencyMiddleware::HEADER, 'idem-conflict-1')
        ->postJson('/api/v1/_test/idempotent', ['v' => 'a'])
        ->assertCreated();

    $response = $this->withHeader(IdempotencyMiddleware::HEADER, 'idem-conflict-1')
        ->postJson('/api/v1/_test/idempotent', ['v' => 'b']);

    $response->assertStatus(Response::HTTP_CONFLICT)
        ->assertJsonPath('error.code', 'idempotency_conflict');
    expect($this->hits->count)->toBe(1);
});

it('does not cache 5xx responses (transient errors)', function (): void {
    $this->withHeader(IdempotencyMiddleware::HEADER, 'idem-5xx-1')
        ->postJson('/api/v1/_test/server-error', ['v' => 1])
        ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);

    $this->withHeader(IdempotencyMiddleware::HEADER, 'idem-5xx-1')
        ->postJson('/api/v1/_test/server-error', ['v' => 1])
        ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);

    expect($this->hits->count)->toBe(2);
    expect(IdempotencyKeyModel::query()->count())->toBe(0);
});

it('persists the cached row in idempotency_keys', function (): void {
    $this->withHeader(IdempotencyMiddleware::HEADER, 'idem-persist-1')
        ->postJson('/api/v1/_test/idempotent', ['v' => 7]);

    $row = IdempotencyKeyModel::query()->where('key', 'idem-persist-1')->first();
    expect($row)->not->toBeNull();
    expect($row?->method)->toBe('POST');
    expect($row?->path)->toBe('/api/v1/_test/idempotent');
    expect($row?->response_status)->toBe(201);
});
