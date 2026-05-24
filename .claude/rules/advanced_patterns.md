# Concurrency

Assume two processes will modify the same data at the same time. Pick the right tool.

---

## Optimistic Locking

Default for most aggregates. Add a `version` column; increment on every write; reject writes whose `version` does not match the loaded value.

```sql
ALTER TABLE orders ADD COLUMN version INT NOT NULL DEFAULT 0;
```

```php
public function save(Order $order): void
{
    $rows = OrderModel::where('id', $order->id->value)
        ->where('version', $order->version)
        ->update([...$this->mapper->toRow($order), 'version' => $order->version + 1]);

    if ($rows === 0) {
        throw new ConcurrencyException('Order was modified by another process');
    }
}
```

Map `ConcurrencyException` to HTTP 409 Conflict so the client can refetch and retry.

## Pessimistic Locking

When the operation is short and contention is high (inventory decrement, balance update).

```php
DB::transaction(function () use ($id, $qty) {
    $row = DB::table('inventory')->where('product_id', $id)->lockForUpdate()->first();
    if ($row->qty < $qty) throw new InsufficientStockException();
    DB::table('inventory')->where('product_id', $id)->update(['qty' => $row->qty - $qty]);
});
```

Always inside a transaction. Always have a timeout.

## Advisory Locks (PostgreSQL)

Coordinate across processes without a row to lock.

```php
DB::statement('SELECT pg_advisory_xact_lock(?)', [crc32('daily-import')]);
```

Released at transaction end. Use for cron jobs that must not overlap.

## Database Constraints

Cheapest concurrency tool. Let the database reject invalid concurrent states:

- Unique indexes for "one of X per Y".
- Check constraints for invariants (`amount >= 0`).
- Foreign keys for referential integrity.
- Exclusion constraints (PostgreSQL) for non-overlapping ranges.

## Idempotency

A retried write must not duplicate. See the Idempotency section below.

## Distributed Locks (Redis)

For coordinating across application instances. Laravel ships with `Cache::lock()`.

```php
$lock = Cache::lock('rebuild-leaderboard', 60);
if ($lock->get()) {
    try {
        // ...
    } finally {
        $lock->release();
    }
}
```

Set an explicit TTL. Always wrap in `try/finally`. Prefer `block()` with a max wait if waiting is acceptable.

## Choosing

| Situation | Tool |
|---|---|
| User edits a profile that another user might also edit | Optimistic locking + 409 |
| Decrement inventory under load | Pessimistic locking + transaction |
| One cron job at a time across nodes | Advisory lock or distributed lock |
| One unique business reference per row | Unique constraint |
| Webhook may fire twice | Idempotency key or state check |
| Long-running workflow with steps | Saga or state machine, not raw locks |

## Anti-Patterns

- Sharing a Redis lock across application versions without migration thought.
- "Read, sleep, write" loops to dodge locks. Use locks properly or use a unique constraint.
- Holding a database lock while making external HTTP calls inside the transaction.
- Optimistic locking without surfacing the conflict to the client (silent overwrites).
# CQRS

Command Query Responsibility Segregation: separate the model that writes from the model that reads.

---

## When to Adopt

CQRS is overkill for most CRUD apps. Adopt it when:

- Read and write shapes diverge enough that one model serves neither well (e.g., dashboard projections aggregating across aggregates).
- Read traffic dominates and benefits from materialized views, denormalized tables, or a separate datastore (Elastic, Redis).
- Audit / event sourcing requirements drive a write side that is fundamentally different from the read side.

Skip it when:

- The read shape is the same as the aggregate.
- The team is small and the read load is modest.
- You just want "Actions". You can have Actions without CQRS.

---

## Two-Sided Model

### Write Side

- **Commands** (`CreateOrderCommand`) and **Command Handlers** (`CreateOrderHandler`).
- Commands are intent objects; handlers orchestrate domain logic and persist via repositories.
- Always validated (Form Request → DTO → Command).
- Returns `void` or an id, never a read shape.

```php
final readonly class PayOrderCommand
{
    public function __construct(public string $orderId, public string $paymentMethod) {}
}

final readonly class PayOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
        private Dispatcher $events,
        private DatabaseManager $db,
    ) {}

    public function handle(PayOrderCommand $cmd): void
    {
        $this->db->transaction(function () use ($cmd) {
            $order = $this->orders->findById(new OrderId($cmd->orderId)) ?? throw new OrderNotFoundException();
            $order->markAsPaid();
            $this->orders->save($order);

            $this->db->afterCommit(
                fn () => $this->events->dispatch(new OrderPaid(new OrderId($cmd->orderId))),
            );
        });
    }
}
```

### Read Side

- **Queries** (`GetOrderQuery`) and **Query Handlers**.
- Returns DTOs (`OrderView`), never aggregates or Eloquent models.
- Reads from optimized projections — read repositories, materialized views, search indexes.
- May bypass the write-side aggregate entirely.

```php
final readonly class GetCustomerDashboardQuery
{
    public function __construct(public string $customerId) {}
}

final readonly class GetCustomerDashboardHandler
{
    public function __construct(private DashboardReadRepository $reads) {}

    public function handle(GetCustomerDashboardQuery $q): DashboardView
    {
        return $this->reads->loadFor(new CustomerId($q->customerId));
    }
}
```

---

## Bus or Direct?

- **Direct** — controllers inject the handler and call it. Simple. Recommended unless a bus brings concrete value.
- **Bus** — `CommandBus` / `QueryBus` with middleware (logging, retries, transactions, audit). Worth it when you have many handlers and consistent middleware, or when you want to ship handlers to a queue (async commands).

Laravel's built-in `Bus::dispatch()` works for both sync handlers and queued jobs. Keep the seam consistent.

---

## Projections

Write side raises events. Projections (read-side processors) update read tables.

```
Command → Aggregate → Event → Projection updates read table → Query reads from read table
```

Projections are **eventually consistent**. The UI must handle reads that lag the write by a few hundred milliseconds — usually fine for dashboards, not fine for "Buy Now → list of my orders".

For after-write reads in the same request, **read your own writes** by querying the write side or holding a short read-side cache invalidation.

---

## Eventual Consistency Pitfalls

- Projection lag confuses users who expect immediate visibility. Communicate it (toast: "Saved. May take a moment to appear.").
- Out-of-order events break projections — consume in deterministic order or design idempotent updates.
- Replayability — keep events long enough to rebuild projections from scratch.

---

## Lite Variant

You can adopt much of the value with less ceremony:

- Use **Actions** for writes (one method per use case).
- Use **read repositories** with DTO projections for reads.
- Skip the bus entirely.

This is "CQRS lite" and fits most Laravel applications.
# Idempotency

Operations that can be retried — webhooks, payment, order creation, message handlers — must produce the same result whether they run once or ten times.

---

## Why

- Network retries duplicate requests.
- Queue redelivery duplicates handlers.
- User clicks "Submit" twice.

A non-idempotent endpoint that creates two orders for one click is a bug.

---

## Strategies

### 1. Idempotency Key (HTTP)

Client sends `Idempotency-Key: <uuid>` on the request. Server stores key + response for 24 hours; replays return the original response without re-running the work.

Schema:

```sql
CREATE TABLE idempotency_keys (
    key          VARCHAR(64)  PRIMARY KEY,
    user_id      UUID         NOT NULL,
    request_hash CHAR(64)     NOT NULL,
    response     JSONB        NOT NULL,
    status_code  INTEGER      NOT NULL,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT now(),
    expires_at   TIMESTAMPTZ  NOT NULL
);
CREATE INDEX ON idempotency_keys (expires_at);
```

Middleware:

```php
final class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if (!$key) return $next($request);

        $hash = hash('sha256', $request->getContent());

        if ($cached = IdempotencyKey::find($key)) {
            if ($cached->request_hash !== $hash) {
                abort(409, 'Idempotency-Key reused with different payload');
            }
            return response($cached->response, $cached->status_code);
        }

        $response = $next($request);
        IdempotencyKey::create([
            'key' => $key,
            'user_id' => $request->user()?->id,
            'request_hash' => $hash,
            'response' => $response->getContent(),
            'status_code' => $response->getStatusCode(),
            'expires_at' => now()->addDay(),
        ]);

        return $response;
    }
}
```

### 2. Unique Constraint at the Database

The cleanest. Use a natural unique key (`reference`, `external_id`, `(user_id, day, type)`).

```php
try {
    OrderModel::create(['reference' => $data->reference, ...]);
} catch (UniqueConstraintViolationException $e) {
    return OrderModel::where('reference', $data->reference)->first();
}
```

### 3. State Check

Before acting, check the aggregate's state.

```php
if ($order->status === OrderStatus::Paid) {
    return;     // already done; no-op
}
$order->markAsPaid();
```

Combine with optimistic locking (version column) to prevent races.

### 4. Job Deduplication

`ShouldBeUnique` keeps duplicate dispatches off the queue.

```php
final class SendReceiptJob implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 3600;
    public function uniqueId(): string { return $this->orderId; }
}
```

For at-least-once delivery, also add a state check inside `handle()`.

---

## Anti-Patterns

- "We will not retry, so idempotency does not matter." Networks retry. Queues retry.
- Idempotency key stored in memory (lost on deploy / restart).
- Long retention with no expiry policy (table grows forever).
- Storing only the key without the request hash — replays with different payloads silently succeed.
# Transactional Outbox

When you need integration events to reliably reach another system after a database write — without dual-write inconsistency.

---

## The Problem

```php
DB::transaction(fn () => $repo->save($order));
$messageBus->publish(new OrderCreated($order->id));   // process crashes here
```

The DB has the order; the downstream never hears about it. Conversely:

```php
$messageBus->publish(...);
DB::transaction(fn () => $repo->save($order));        // transaction fails
```

The downstream got an event for an order that does not exist.

This is the **dual-write problem**.

---

## The Pattern

Write the event to an outbox table **inside the same transaction** as the aggregate. A worker reads the outbox and publishes externally.

```sql
CREATE TABLE outbox_messages (
    id            UUID         PRIMARY KEY,
    aggregate_id  VARCHAR(64)  NOT NULL,
    type          VARCHAR(128) NOT NULL,
    payload       JSONB        NOT NULL,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    published_at  TIMESTAMPTZ  NULL,
    attempts      INT          NOT NULL DEFAULT 0,
    last_error    TEXT         NULL
);
CREATE INDEX ON outbox_messages (published_at) WHERE published_at IS NULL;
```

Action:

```php
DB::transaction(function () use ($order) {
    $this->orders->save($order);
    OutboxMessage::create([
        'id' => Str::uuid(),
        'aggregate_id' => $order->id->value,
        'type' => OrderCreated::class,
        'payload' => ['order_id' => $order->id->value, 'occurred_at' => now()->toIso8601String()],
    ]);
});
```

Worker (scheduled command or queue job):

```php
final class PublishOutboxJob implements ShouldQueue
{
    public function handle(MessagePublisher $publisher): void
    {
        OutboxMessage::query()
            ->whereNull('published_at')
            ->orderBy('created_at')
            ->limit(100)
            ->lockForUpdate()
            ->get()
            ->each(function (OutboxMessage $msg) use ($publisher) {
                try {
                    $publisher->publish($msg->type, $msg->payload);
                    $msg->update(['published_at' => now()]);
                } catch (Throwable $e) {
                    $msg->increment('attempts');
                    $msg->update(['last_error' => $e->getMessage()]);
                }
            });
    }
}
```

Schedule:

```php
Schedule::job(PublishOutboxJob::class)->everyMinute();
```

---

## Guarantees

- **At-least-once** delivery. Consumers must be idempotent (see the Idempotency section).
- Ordering per aggregate if you publish in `created_at` order and use a single worker per partition.
- No dual-write inconsistency.

## Variations

- **CDC (Change Data Capture)** — Debezium or similar reads the WAL/binlog and publishes. The outbox table becomes a CDC source. Often preferred at scale.
- **In-process** — small systems can publish directly with `DB::afterCommit()`. The outbox is overkill until you need cross-process or cross-region durability.

## Pitfalls

- Forgetting to set `published_at` on success.
- Worker that does not back off on persistent failures (poison message storms).
- Outbox table without retention — clean up published rows after a grace period.
- Not handling out-of-order delivery on the consumer side.
# Resilience

External calls fail. Networks partition. Downstreams degrade. Build for it.

---

## Timeouts

Every external call has a timeout. There is no acceptable default of "wait forever".

```php
Http::timeout(5)              // total time
    ->connectTimeout(2)       // socket connect
    ->get('https://api.example.com/...');
```

- HTTP: 2–10 seconds depending on the operation.
- Database: connection timeout + statement timeout (set on the connection or per-query).
- Cache (Redis): 1 second connect, 500 ms read.
- External SDK: configure or wrap with `pcntl_signal` if the SDK ignores timeouts.

## Retries with Backoff

Retry only on transient errors (`5xx`, connection refused, timeout). Never retry on `4xx` (the request is wrong; retrying will not fix it).

```php
Http::retry(
    times: 3,
    sleepMilliseconds: 200,
    when: fn ($e) => $e instanceof ConnectionException,
)->get(...);
```

Backoff strategies:

- **Linear**: 200 ms, 400 ms, 600 ms.
- **Exponential**: 200 ms, 400 ms, 800 ms, 1600 ms.
- **Exponential with jitter** (preferred): exponential base + random offset to spread load.

## Circuit Breaker

When a downstream is failing repeatedly, stop calling it for a cooldown window. Saves your latency, gives the downstream room to recover.

States:

- **Closed** — calls pass through; failures counted.
- **Open** — calls fail fast for the cooldown window; do not contact the downstream.
- **Half-open** — after cooldown, allow a probe call; success closes, failure reopens.

Implementation: a state machine in Redis with TTL on the open state, or a package (`prewk/laravel-circuit-breaker`, `ackintosh/ganesha-laravel`).

```php
$breaker = app(CircuitBreaker::class)->for('shipping-api');

if ($breaker->isOpen()) {
    throw new DownstreamUnavailableException();
}

try {
    $response = $http->post(...);
    $breaker->recordSuccess();
} catch (Throwable $e) {
    $breaker->recordFailure();
    throw $e;
}
```

## Bulkheads

Isolate failure domains. A failing downstream should not consume all your queue workers.

- Separate queues per integration: `mail`, `shipping`, `analytics`.
- Separate Horizon `processes` per queue.
- Connection pools per integration if using HTTP/2 or persistent connections.

## Rate Limit Yourself (Outbound)

If a downstream allows 100 req/min, throttle to 80 req/min and never get 429'd. Use Redis token bucket or `Cache::lock()` based limiting.

```php
RateLimiter::attempt('shipping-api:'.$tenantId, 80, function () use ($http) {
    return $http->post(...);
}, 60);
```

## Graceful Degradation

When a non-critical downstream is down, degrade rather than fail:

- Cached snapshot ("last known balance: $1,245 (updated 5 minutes ago)").
- Disabled feature with explanation ("Shipping estimates temporarily unavailable").
- Async fallback (queue the work, return 202 with a poll URL).

Critical paths (payment, auth) fail loudly with a clear error code and a human message.

## Idempotent Retries

Retries are safe only when the operation is idempotent. See the Idempotency section.

For non-idempotent operations:

- Send an idempotency key.
- Track operation status; check before retrying.

## Observability

Every retry, every breaker state change, every timeout emits a metric and a structured log.

```php
Log::info('shipping.retry', [
    'attempt' => $attempt,
    'reason' => $reason,
    'tenant' => $tenantId,
]);
```

## Anti-Patterns

- Infinite retries (`while (true) { retry(); sleep(1); }`).
- Retrying `4xx` responses.
- Catching `Throwable` and silently continuing — surfaces the problem when it is too late.
- "Just increase the timeout to 60 seconds." Slow is the new down.
- No circuit breaker on a downstream that has ever had an incident.
