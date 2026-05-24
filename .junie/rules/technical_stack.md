# API Design

REST-style HTTP API. Predictable, versioned, paginated, idempotent where it matters.

---

## Versioning

All endpoints under `/api/v1/...`. Never expose `/api/...` without a version. When breaking changes are needed, bump to `/api/v2/...` and run versions in parallel until clients migrate.

## Controller Shape

Thin. One controller per use case at Medium/Complex tier; `Resource\Controller` only at Simple tier.

```php
final class CreateOrderController
{
    public function __invoke(
        CreateOrderRequest $request,
        CreateOrderAction $action,
    ): JsonResponse {
        $orderId = $action->handle(CreateOrderData::fromRequest($request));

        return new JsonResponse(
            data: ['data' => ['id' => $orderId->value]],
            status: Response::HTTP_CREATED,
            headers: ['Location' => route('orders.show', $orderId->value)],
        );
    }
}
```

Controller responsibilities:

1. Validate (delegated to Form Request).
2. Authorize (delegated to Form Request `authorize()`).
3. Build the DTO from the validated request.
4. Invoke the Action.
5. Return a Response or Resource.

## Response Format

Single, consistent envelope.

```json
{
  "data": { "id": "...", "items": [...] },
  "meta": { "page": 1, "per_page": 20, "total": 137 },
  "links": { "next": "/api/v1/orders?page=2" }
}
```

For collection responses, `data` is an array. Use `JsonResource::collection()` and the built-in pagination meta when using API Resources.

## Errors

Stable error code (machine-readable) + human message.

```json
{
  "error": {
    "code": "order_not_found",
    "message": "Order not found",
    "trace_id": "req_01H..."
  }
}
```

Validation (422):

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Map exceptions to HTTP statuses globally in `{APP_ROOT}/bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(fn (OrderNotFoundException $e) => response()->json([
        'error' => ['code' => 'order_not_found', 'message' => $e->getMessage()],
    ], 404));
})
```

## Status Codes

- `200` GET / PATCH success with body
- `201` POST that creates a resource (with `Location` header)
- `202` accepted for async processing
- `204` DELETE / PATCH success with no body
- `400` malformed request (rare; usually `422`)
- `401` unauthenticated
- `403` authenticated but not authorized
- `404` resource not found
- `409` conflict (e.g., optimistic lock mismatch)
- `422` validation failure
- `429` rate limited
- `500` unhandled server error

## Pagination

Required for any list endpoint.

```
GET /api/v1/orders?page=1&per_page=20
```

- Default `per_page` if missing (e.g., 20). Cap at 100.
- For very large datasets, prefer cursor pagination: `?cursor=abc123`.

## Filtering and Sorting

Explicit, allow-listed.

```
GET /api/v1/orders?filter[status]=paid&sort=-created_at
```

Use `spatie/laravel-query-builder` to declare allowed filters/sorts safely. Never accept arbitrary column names from the query string.

## Idempotency

Critical write endpoints (`POST /orders`, `POST /payments`) must accept an `Idempotency-Key` header. Server stores the key + response for 24h; replay returns the original response.

See `rules/advanced_patterns.md` (Idempotency section).

## Rate Limiting

Default tier per token + per route in `{APP_ROOT}/routes/api.php`:

```php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () { ... });
```

Stricter limits for write or expensive endpoints.

## Resources / Serialization

Never return Eloquent models directly. Use `JsonResource` or a DTO.

```php
final class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'status'     => $this->status->value,
            'total'      => ['amount' => $this->total_cents, 'currency' => 'USD'],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

Field names are snake_case in JSON (Laravel default). Be consistent with the rest of the API.

## Documentation

Every endpoint has an OpenAPI entry (Scribe, l5-swagger, or hand-written `openapi.yaml`). Document request, response, errors, and example payloads. PRs touching an endpoint update the spec.
# Database

PostgreSQL preferred. MySQL acceptable. SQLite for tests only.

---

## Migrations

- One migration per change. Never edit a shipped migration.
- Reversible: implement `down()` unless the change is genuinely irreversible (then state why in a comment).
- Use schema builder; raw SQL only for database-specific features (`->using('CAST(...)')`, partial indexes, GIN indexes).
- Name migrations clearly: `2026_04_24_120000_add_payment_status_to_orders_table.php`.

## Naming

- Tables: snake_case, plural (`orders`, `order_items`).
- Columns: snake_case (`created_at`, `customer_id`).
- Foreign keys: `{singular_table}_id` (`customer_id`).
- Pivot tables: alphabetical, singular (`role_user`).
- Indexes: `{table}_{cols}_index`, unique: `{table}_{cols}_unique`.

## Data Types

| Use case | Type |
|---|---|
| Identifiers | `uuid` (preferred) or `bigint` |
| Money amount | `bigint` (cents) plus `string(3)` currency |
| Status enums | `string(32)` with check constraint, or PG enum if rarely changing |
| Timestamps | `timestamptz` (PostgreSQL); `timestamp` with explicit UTC handling (MySQL) |
| Long text | `text` (no `varchar(big number)`) |
| JSON | `jsonb` (PostgreSQL); `json` (MySQL 8+) |

Avoid `float` / `double` for money. Avoid `enum` columns at the database level — they are painful to migrate; store as `string` with a check constraint or rely on application-level enum.

## Indexes

- Index every foreign key (Laravel does not do this automatically).
- Composite indexes for the most common WHERE + ORDER BY combination.
- Partial indexes for hot subsets (`WHERE status = 'active'`).
- Covering indexes (`INCLUDE`) for read-heavy queries on PostgreSQL.
- Drop unused indexes — they slow writes and waste storage.

```php
$table->index(['status', 'created_at']);                          // composite
$table->unique(['customer_id', 'reference']);                     // business uniqueness
$table->rawIndex('lower(email)', 'customers_email_lower_index');  // expression index
```

## Constraints

- `NOT NULL` by default; nullable only when the absence has meaning.
- Foreign keys with `cascade`, `restrict`, or `set null` chosen deliberately.
- Check constraints for invariants the database can enforce (`amount_cents >= 0`).

## Migrations and Code

- Never edit a model's `$casts` without a migration that backfills existing rows in the new shape.
- Backfills run in batches inside their own command, not inline in a migration that may time out.
- New nullable columns first, then a follow-up backfill, then a separate migration to make them NOT NULL.

## Seeders and Factories

- Factories for tests (`OrderFactory`).
- Seeders for local development and CI fixtures (`DatabaseSeeder`, `ProductionSampleSeeder`).
- Never seed production data through Laravel seeders unless the data is small and the seeder is idempotent.

## Performance

- Run `EXPLAIN ANALYZE` on any new query that reads from a large table.
- Watch for sequential scans on large tables.
- For full-text search, use `tsvector` + GIN (PostgreSQL) or a dedicated engine (Meilisearch, Typesense). Do not use `LIKE '%term%'` on millions of rows.

## Soft Deletes

- Use only when business needs the record to survive (audit, undo). Otherwise, hard delete.
- Soft-deleted rows must be filtered out of unique indexes (`WHERE deleted_at IS NULL` on the index).

## Connection Pooling

- High-throughput services: PgBouncer in transaction mode for PostgreSQL.
- Be aware of session-level features that pooling breaks (advisory locks across requests).
# Eloquent

Eloquent is an Infrastructure concern. It is excellent at mapping rows to objects and expressing relations. It is a poor place for business rules.

---

## Placement

- All Eloquent models live in `{APP_ROOT}/app/Infrastructure/{Ctx}/Persistence/Eloquent/Models/` (or `{APP_ROOT}/app/Models/` for Simple tier).
- Repository implementations live in `{APP_ROOT}/app/Infrastructure/{Ctx}/Persistence/Eloquent/Repositories/Eloquent{Name}Repository.php`.
- Mappers live in `{APP_ROOT}/app/Infrastructure/{Ctx}/Persistence/Eloquent/Mappers/`.
- Models must not be imported into Domain or Application code in Medium or Complex tiers. Application receives Domain objects or DTOs.

## Model Responsibilities

Allowed:

- `$fillable` / `$guarded`, `$casts`, `$hidden`
- Relations (`hasMany`, `belongsTo`, ...)
- Query scopes (`scopeActive`, `scopePaid`) when they express reusable filters
- Accessors and mutators for trivial presentation (formatting, not validation)
- Factory state methods used in tests

Not allowed:

- Methods that orchestrate use cases (`pay()`, `refund()`) — those belong in the Domain entity or Action
- Direct dispatching of emails, jobs, or external calls
- Validation rules (lives in Form Request or DTO)

## N+1 Prevention

- Use `with()` on index queries. Use `load()` when needed after fetch.
- Enable `Model::preventLazyLoading()` in non-production environments (via `AppServiceProvider::boot()`).
- For lists, prefer `select()` with only the columns you need, or a DTO projection.

```php
// Good
$orders = OrderModel::with(['items:id,order_id,sku,qty'])
    ->where('status', OrderStatus::Paid)
    ->orderByDesc('created_at')
    ->paginate(50);
```

## Large Datasets

- `paginate()` for user-facing lists (max 100 per page).
- `chunkById()` for bulk processing (not `chunk()` when the underlying query may change).
- `lazy()` / `cursor()` for exports and reports.
- Never `get()` without a `limit()` on tables that may exceed a few hundred rows.

## Updates

- Use explicit updates: `$model->fill([...])->save()` or `OrderModel::where(...)->update([...])`.
- `save()` vs `update()` — prefer `save()` after `fill()` so model events fire consistently.
- Mass update without events: `->update([...])` — document why events are being skipped if you choose this path.

## Transactions

```php
DB::transaction(function () use ($data) {
    $order = OrderModel::create([...]);
    $order->items()->createMany([...]);
});
```

- Nested transactions use savepoints; still avoid unless deliberately composing.
- Do not wrap queue dispatch in a transaction. Use `DB::afterCommit(fn () => Bus::dispatch(...))` for write-triggered side effects.

## Relations

- Always type the return: `public function items(): HasMany`.
- Avoid `hasManyThrough` chains that bypass aggregate boundaries — fetch and map in the repository instead.
- Polymorphic relations only when the cost of a flat schema exceeds the cost of the type column + uuid indirection.

## Enums and Casts

- Use native PHP enums with `use Illuminate\Database\Eloquent\Casts\AsStringable`, `AsCollection`, `AsArrayObject`.
- `$casts = ['status' => OrderStatus::class, 'created_at' => 'immutable_datetime']`.

## Querying

- Prefer query builder chain over `DB::raw()`. Use raw only for database-specific functions with a comment explaining the escape hatch.
- Named scopes for reuse; one-off queries inline.
- Repository methods encapsulate cross-table queries; controllers and Actions never write `OrderModel::where(...)->get()` directly in Complex tier.

## Serialization Boundary

Never return an Eloquent model directly to the HTTP response at Medium or Complex tier. Wrap in an `API Resource` or a DTO.
# Performance-Critical Code

For endpoints, jobs, and queries that may run thousands of times per minute or process millions of rows.

---

## Queries

- Always set explicit columns. No `SELECT *`.
- Always have a `LIMIT` or pagination.
- Always justify joins on large tables; consider denormalization or read-side projections.
- Use prepared statements (Eloquent does this by default; raw queries must use parameter binding).
- Verify execution plan with `EXPLAIN (ANALYZE, BUFFERS)`.

```sql
SELECT id, status, created_at
FROM orders
WHERE customer_id = $1 AND status = 'paid'
ORDER BY created_at DESC
LIMIT 50;
```

Required index: `(customer_id, status, created_at DESC)`.

## Memory and Streaming

- Stream responses with `StreamedResponse` or `ResponseFactory::streamDownload()` for exports.
- Process imports in chunks; flush and clear after each chunk.

```php
foreach (LazyCollection::make(fn () => readCsv($path))->chunk(1000) as $chunk) {
    DB::transaction(fn () => OrderModel::insert($chunk->all()));
}
```

## Backpressure

- Cap concurrency on queues per business priority (Horizon `processes` per queue).
- Rate-limit downstream calls (`Http::retry(3, 100)`, exponential backoff).
- Drop or shed low-priority work under sustained overload — the system stays up.

## External Calls

Every external call sets:

- **Timeout** — `Http::timeout(5)`.
- **Retries** with backoff — `Http::retry(3, 200)`.
- **Circuit breaker** — block calls when error rate exceeds threshold (use a package or hand-rolled state in Redis).

```php
Http::baseUrl(config('services.shipping.url'))
    ->timeout(5)
    ->connectTimeout(2)
    ->retry(3, 200, fn ($e) => $e instanceof ConnectionException)
    ->withHeaders(['Authorization' => 'Bearer '.config('services.shipping.token')])
    ->post('/labels', $payload);
```

See `rules/advanced_patterns.md` (Resilience section).

## Caching for Hot Reads

- Read-through cache for endpoints with predictable cache keys.
- Per-tenant cache keys to avoid cross-tenant leaks.
- Stampede protection (lock on cache miss, single regenerator).

```php
return Cache::lock("order:$id:rebuild", 10)->block(2, function () use ($id) {
    return Cache::remember("order:$id", 60, fn () => $this->loadOrder($id));
});
```

## Batching

- Batch DB inserts: `Model::insert([...])` not `Model::create()` in a loop.
- Batch jobs via `Bus::batch([...])->dispatch()` for fan-out.
- Aggregate metrics emission to avoid one UDP packet per event.

## Observability

Production-critical code emits:

- Structured logs with `request_id`, `trace_id`, `user_id`, duration.
- Metrics: counter (events), gauge (queue depth), histogram (latency).
- Spans: distributed tracing through OpenTelemetry, or Pulse for in-Laravel tracing.

```php
Log::withContext(['order_id' => $orderId->value, 'trace_id' => request()->header('X-Trace-Id')]);
```

## Octane and Workers

- For very high RPS endpoints, Laravel Octane (Swoole/RoadRunner/FrankenPHP) keeps the framework warm.
- Workers must be stateless. Avoid static state, request-scoped singletons that leak.

## Anti-Patterns

- Generating thousands of jobs in a synchronous request.
- Long-running queries in HTTP context (move to a queue, return job id, poll).
- Queueing a job per row of a million-row table without batching.
- Caching write paths.
# Performance

Default assumption: tables grow without bound, traffic doubles, and someone is going to call your endpoint in a tight loop.

---

## Queries

- Eager-load relations the response will use.
- `Model::preventLazyLoading()` in non-prod to surface N+1 in development.
- `select()` only the columns you need on hot paths.
- Index every column that appears in `WHERE`, `ORDER BY`, or `JOIN ON` for queries that run often.
- Avoid `count()` on large tables when you only need "is there one?". Use `exists()`.

## Pagination

- Mandatory on every collection endpoint.
- Cursor pagination for very large datasets or infinite scroll.
- Offset pagination is fine up to ~10k rows; beyond that, switch to cursor.

## Memory

- Never load thousands of rows into memory at once.
- `lazy()` / `cursor()` for streaming.
- `chunkById()` (not `chunk()`) for batch processing of mutating queries.

## Caching

- Cache reads at the Infrastructure boundary, never inside Domain.
- Tag caches by aggregate (`cache()->tags(['order:'.$id])`).
- Invalidate explicitly on write; do not rely on TTL for correctness.
- `Cache::flexible()` (Laravel 11+) for stale-while-revalidate.

## Asynchronous Processing

Push to a queue:

- Email, SMS, push notifications
- Reports, exports, large imports
- External API integrations
- Webhook delivery

See the Jobs and Queues section in `rules/technical_stack.md`.

## Bulk Writes

- `insert()` for many rows at once (no events, no Eloquent overhead).
- `upsert()` for idempotent batch upserts.
- Disable model events (`->withoutEvents(...)`) if they fire per row in a loop.

## Avoid

- `findAll()` — does not exist for a reason.
- `with('*')` — eager-loads everything.
- `whereIn('id', $thousand_ids)` — chunk it (Laravel rewrites to multiple queries beyond a few thousand).
- `orderBy('RAND()')` — kills the database. Use sample tables or app-level sampling.

## Profile Before Optimizing

- Laravel Telescope (local) and Pulse (prod) for query timing.
- Clockwork or Debugbar for in-browser profiling.
- `DB::listen()` or query log when chasing N+1.
- Always measure on data shaped like production (anonymized snapshots, not 50-row dev DBs).

See `rules/performance-critical.md` for high-traffic specifics.
# Stack and Tooling

Default to these versions unless the project pins something older. Do not introduce new top-level packages without justification.

---

## Runtime

- **PHP 8.4** — readonly classes, property hooks, asymmetric visibility, `#[\Override]`, `new in initializer`
- **Laravel 13** — Laravel 11+ streamlined skeleton (`bootstrap/app.php`, minimal providers)

## Required Dev Tools

- **Laravel Pint** — formatting (PSR-12 + Laravel preset)
- **PHPStan** level 8 or **Larastan** — static analysis
- **Pest 4** — testing (PHPUnit compatible if the project already uses PHPUnit)
- **Rector** — optional but recommended for upgrades

## Preferred First-Party Packages

- `laravel/sanctum` — API tokens for SPA / mobile
- `laravel/horizon` — queue dashboard (Redis)
- `laravel/telescope` — local debugging only; never ship to prod
- `laravel/pulse` — production observability

## Preferred Community Packages

Add only when the use case appears:

- `spatie/laravel-data` — rich DTOs with validation and transformation
- `spatie/laravel-permission` — role/permission layer on top of Gates
- `spatie/laravel-query-builder` — safe filtering/sorting from query strings
- `spatie/laravel-medialibrary` — file attachments
- `league/flysystem-*` — storage adapters (already bundled with Laravel)

## Do Not Introduce

- ORMs other than Eloquent in new code (e.g., Doctrine) unless the project already uses them
- Service locator packages
- Generic "helper" packages that duplicate Laravel features

---

## Running Things

Always propose commands that are standard for a Laravel 13 project:

```
php artisan test              # tests
./vendor/bin/pint             # format
./vendor/bin/phpstan analyse  # static analysis
php artisan migrate:fresh --seed
php artisan queue:work
```

If the project runs in Docker, run the commands inside the PHP container. Ask for the container name once, then remember it for the session.

---

## PHP 8.4 Usage Rules

- Use `readonly class` for DTOs, commands, queries, and value objects.
- Use property hooks where they replace a trivial getter/setter that validates or normalizes.
- Use asymmetric visibility (`public private(set)`) instead of manual setters when the property is immutable after construction but set in `__construct`.
- Use `#[\Override]` on all methods that implement or override a parent to catch typo'd signatures.
# Laravel Conventions

Use Laravel's built-in features before reaching for custom abstractions. Laravel is the Infrastructure; it does not belong in Domain or Application.

---

## Container and Dependency Injection

- Use constructor injection for every dependency. Autowiring resolves concrete classes; use `bind()` / `singleton()` in a service provider for interfaces.
- Do not use `app()`, `resolve()`, or facades for service location inside classes (only inside closures that the container evaluates, and only when constructor DI is impossible).
- Bind interfaces in a module-scoped service provider:

```php
final class OrderServiceProvider extends ServiceProvider
{
    public array $bindings = [
        OrderRepository::class => EloquentOrderRepository::class,
    ];
}
```

- Singletons only for stateless services with expensive construction (HTTP clients, SDK wrappers). Never singleton a stateful object.

## Service Providers

- Providers only wire things up. No business logic in `boot()` or `register()`.
- Keep them small and feature-scoped. Prefer `app/Modules/<Mod>/Providers/<Mod>ServiceProvider.php` over a single `AppServiceProvider`.

## Routing

- Use `Route::get()` with invokable controllers where possible: `Route::post('/orders', CreateOrderController::class)`.
- Group by module: `Route::prefix('api/v1')->group(base_path('app/Modules/Order/routes.php'))`.
- Always version APIs: `/api/v1/...`. Never expose unversioned endpoints.

## Facades

- Acceptable in UI (controllers, console commands) and Infrastructure adapters.
- Forbidden in Domain. Avoid in Application — inject `Dispatcher`, `DatabaseManager`, `Repository` (cache) instead.

## Eloquent Models

See `rules/eloquent.md`. Keep models in `Infrastructure`. Models are a persistence shape, not the domain.

## Configuration

- All tunables in `config/*.php`; never hardcode secrets or toggles.
- Access via `config('orders.timeout_seconds')`, not `env()` outside config files.
- `env()` is only read inside `config/*.php` so the config can be cached.

## Queues

- Use `dispatch()` or `Bus::dispatch()` from Application code only when it is the use case's primary effect (e.g., `SendNewsletter` is dispatched from a controller).
- For side effects triggered by a state change, raise a Domain event and listen to it with a Listener that dispatches the Job (`ShouldQueue`).

## Cache

- Cache reads at Infrastructure boundary. Domain must not know about cache.
- Use tagged caches or explicit invalidation on write, never rely on TTL alone for correctness.
- `Cache::flexible(...)` (Laravel 11+) for stale-while-revalidate reads.

## Middleware

- Cross-cutting concerns only: auth, throttling, correlation ID, content negotiation, CSRF.
- Do not put business logic in middleware.

## Console

- Each command is one file, one responsibility. Delegate the work to an Action — the command is a UI adapter over it.
- Schedule in `routes/console.php` or module-scoped `bootstrap/console.php`.

## Localization

- All user-facing strings through `__()` or `trans()`; never hardcoded English text in Blade or API responses.
- Error codes are machine-readable (`order_not_found`), separate from human messages.

## Artisan Generators

Prefer custom generators or the `lunarstorm/laravel-ddd` toolkit for DDD modules when the project adopts it. Otherwise, Laravel's built-in `make:*` commands are fine for Simple and Medium tiers.
# Events and Listeners

Events decouple a state change from its side effects. They are not a free-for-all message bus.

---

## Two Kinds of Events

1. **Domain events** — business facts. Past tense. Live in `Domain/{Ctx}/Event/`.
   - `OrderPaid`, `SubscriptionCancelled`, `InvoiceIssued`.
   - Raised by entities or Actions after a successful write.
2. **Framework events** — Laravel lifecycle (`Authenticated`, `MessageSent`, `MigrationStarted`).
   - Listen to these in Infrastructure for cross-cutting concerns.

Do not conflate the two. A `UserRegisteredEvent` raised by Fortify is a framework-level signal; the Domain may have its own `NewCustomerRegistered` that the Application raises after the use case completes.

---

## Raising Domain Events

Raise from the Application layer, after the transaction commits.

```php
final readonly class PayOrderAction
{
    public function handle(PayOrderData $data): void
    {
        $this->db->transaction(function () use ($data) {
            $order = $this->orders->findById(new OrderId($data->orderId))
                ?? throw new OrderNotFoundException();
            $order->markAsPaid();
            $this->orders->save($order);
        });

        $this->events->dispatch(new OrderPaid(new OrderId($data->orderId), new DateTimeImmutable()));
    }
}
```

Dispatch **after** the transaction so listeners never run on a rolled-back write. Equivalent shortcut:

```php
DB::afterCommit(fn () => $this->events->dispatch(new OrderPaid(...)));
```

## Listener Rules

- One listener per side effect. Name it for the effect: `SendReceiptOnOrderPaid`, `NotifyWarehouseOnOrderPaid`.
- Listeners are registered in `EventServiceProvider` or via `#[\Illuminate\Events\Attributes\AsEventListener]`.
- Listeners that do I/O implement `ShouldQueue` so the event dispatch is fast.
- Do not put business logic in listeners. Call an Action: `SendReceiptAction::handle(...)`.

```php
final class SendReceiptOnOrderPaid implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private SendReceiptAction $sendReceipt) {}

    public function handle(OrderPaid $event): void
    {
        $this->sendReceipt->handle(new SendReceiptData($event->orderId->value));
    }
}
```

## Do Not Abuse Events

- Events are for **side effects**, not workflow control. If step B must run after step A and you need its result, call B from A, do not chain events.
- Events are asynchronous in spirit even when dispatched synchronously — do not rely on ordering of multiple listeners.
- Do not pass full entities in events; pass ids. The listener refetches.

## Transactional Outbox

For events that must reach another system reliably, use the outbox pattern (write event row in the same transaction, worker publishes). See `advanced/outbox.md`.

## Eloquent Observers vs Domain Events

| Use observers for | Use domain events for |
|---|---|
| Cache invalidation | Send receipt |
| Logging / auditing | Notify warehouse |
| Denormalization / counters | Trigger workflow |
| Validation (rarely; prefer Form Request + Domain) | Integration to other bounded contexts |

Observers are fine for framework-level glue. Business events belong in the Domain layer, dispatched by the Application.

## Testing

```php
Event::fake();
app(PayOrderAction::class)->handle($data);
Event::assertDispatched(
    OrderPaid::class,
    fn (OrderPaid $e) => $e->orderId->value === $orderId,
);
```

When asserting the **listener** ran, use `Bus::fake()` / `Queue::fake()` for the queued job instead of letting the listener fire.
# Jobs and Queues

Push heavy work out of the request cycle. Keep jobs idempotent.

---

## When to Queue

Queue it when:

- External API call that may be slow, fail, or retry.
- Email, SMS, push notification.
- Report generation, large exports.
- Bulk operations that touch many rows.
- Anything that should not delay the HTTP response.

Do not queue it when:

- The user must see the result before the response (keep it sync).
- It is a pure in-memory calculation.
- It is a transactional write the user expects to be durable before the 201 returns.

## Job Structure

```php
final class SendOrderReceiptJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 5;
    public int $backoff = 30;       // seconds; use array for escalating: [10, 30, 60]
    public int $timeout = 60;
    public int $maxExceptions = 3;

    public function __construct(public readonly string $orderId) {}

    public function handle(OrderReceiptSender $sender): void
    {
        $sender->send(new OrderId($this->orderId));
    }

    public function uniqueId(): string
    {
        return $this->orderId;     // implement ShouldBeUnique to prevent duplicates
    }
}
```

Rules:

- Carry **ids**, not full models. Re-fetch inside `handle()`. Models serialize via `SerializesModels` with id references, but explicit ids are clearer and safer.
- Constructor properties `readonly`.
- `handle()` gets its dependencies via method injection.
- Set `$tries`, `$timeout`, `$backoff` explicitly. Do not rely on defaults.

## Idempotency

Jobs may run more than once. `SendOrderReceiptJob` must not send two emails. Strategies:

- **Deduplication by id** — record `order_id` in a `processed_jobs` table; skip if present.
- **State-based** — check the aggregate before acting (`if ($order->receiptSent) return;`).
- **Unique queue** — `ShouldBeUnique` with `uniqueFor` to prevent parallel duplicates.

See `advanced/idempotency.md`.

## Dispatching

Prefer dispatching from an Event Listener rather than directly from an Action, so the Domain event is the source of truth.

```php
final class SendReceiptOnOrderPaid
{
    public function handle(OrderPaid $event): void
    {
        SendOrderReceiptJob::dispatch($event->orderId->value);
    }
}
```

Dispatch from Action directly only when the job is the primary effect of the use case (e.g., `SendNewsletterAction` dispatches `SendNewsletterJob` to each subscriber).

## Transactions

Use `DB::afterCommit(fn () => Job::dispatch(...))` when a job must only run after the enclosing transaction commits. Laravel can also be configured with `after_commit => true` on the connection.

## Retries and Failures

- `failed(Throwable $e)` on the job to record the failure and notify operators.
- `$backoff` must grow (do not hammer a failing downstream every 5 seconds for an hour).
- Dead-letter queue (`failed_jobs` table) monitored in Horizon.

## Horizon

- Configure queues by business priority: `high`, `default`, `low`, `reports`.
- Assign jobs to queues deliberately: transactional > marketing.
- Metrics and alerts via Horizon + Pulse.

## Testing

```php
Queue::fake();
app(PayOrderAction::class)->handle($data);
Queue::assertPushed(SendOrderReceiptJob::class, fn ($j) => $j->orderId === $orderId);
```

For end-to-end: `Queue::fake()->except(SomeJob::class)` when you need one job to run synchronously.
# Repositories

Repositories encapsulate persistence. They do not contain business logic.

---

## When to Introduce a Repository

- **Simple tier** — skip. Use Eloquent directly in Action or Controller.
- **Medium tier** — usually skip. Inject the Eloquent model class into the Action if a single shared query helper makes sense.
- **Complex tier** — interface in Domain, implementation in Infrastructure. Always.

---

## Interface (Domain)

Narrow. Aggregate-scoped. Returns Domain objects.

```php
interface OrderRepository
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function nextId(): OrderId;
}
```

Avoid:

- `findAll()` — no such thing in a system with real data.
- `findByXAndYAndZ()` — fat interface. Extract a query object or a read repository.
- Methods that return `Model` or `Collection` of models.

## Implementation (Infrastructure)

```php
final class EloquentOrderRepository implements OrderRepository
{
    public function __construct(private OrderMapper $mapper) {}

    public function save(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $row = $this->mapper->toRow($order);
            $model = OrderModel::query()->updateOrCreate(['id' => $order->id->value], $row);
            $this->mapper->syncChildren($model, $order);
        });
    }

    public function findById(OrderId $id): ?Order
    {
        $model = OrderModel::with('items')->find($id->value);
        return $model ? $this->mapper->toDomain($model) : null;
    }

    public function nextId(): OrderId
    {
        return OrderId::generate();
    }
}
```

## Mappers

A dedicated `OrderMapper` translates between the Eloquent model and the Domain entity. Keep mapping explicit — do not let entities know how they are persisted.

## Read Repositories (CQRS Flavor)

Split read and write when the read shapes diverge from the write model.

```php
interface OrderReadRepository
{
    /** @return Paginator<OrderListView> */
    public function recentForCustomer(CustomerId $id, int $perPage): Paginator;
    public function dashboardSummary(DateRange $range): DashboardView;
}
```

Reads return DTOs (`OrderListView`), not aggregates. They are optimized for the UI shape.

## Projections

Prefer SQL projections to loading full aggregates for list screens.

```php
OrderModel::query()
    ->select(['id', 'total_cents', 'status', 'created_at'])
    ->where('customer_id', $customerId->value)
    ->latest()
    ->paginate(20)
    ->through(fn ($row) => new OrderListView($row->id, $row->total_cents, $row->status));
```

## Anti-Patterns

- **Fat repository** — 30 `findBy*` methods. Extract read repositories or query objects.
- **Generic `Repository` base class with magic `findBy*`** — hides intent and creates N+1 traps.
- **Business rules inside a repository** — "if customer is VIP, use this query". Decide in Domain or Application; pass parameters.
- **Repository returning Eloquent models across the boundary** — breaks the abstraction; inject the model class directly if that is what you want.
