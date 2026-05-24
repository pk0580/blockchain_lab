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

See `.claude/rules/advanced_patterns.md` (Idempotency section).

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

- All Eloquent models live in `{APP_ROOT}/app/Infrastructure/Persistence/Eloquent/Models/` (or `{APP_ROOT}/app/Models/` for Simple tier).
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

See `.claude/rules/advanced_patterns.md` (Resilience section).

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
