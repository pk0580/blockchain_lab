# Testing

Detect testing framework (Pest or PHPUnit) before writing tests. All business logic is testable; if it is not, refactor.

---

## Detection

- **Pest:** Search for `pestphp/pest` in `{APP_ROOT}/composer.json` or check for `{APP_ROOT}/tests/Pest.php`.
- **PHPUnit:** Default if Pest is not found. Use PHPUnit 12 attributes.

---

## Test Pyramid

- **Unit** — Domain layer. No framework boot. Fast (sub-millisecond per test).
- **Integration** — Infrastructure adapters (repositories, HTTP clients, queue handlers) against real dependencies (test DB, mock HTTP).
- **Feature** — HTTP endpoints, end-to-end through the container. The bulk of the suite.
- **Architecture** — Pest `arch()` rules to enforce layer boundaries in CI.

Heavier weight at the feature layer is fine for Laravel apps; the framework is itself the integration surface for most code.

---

## Pest Examples

### Unit (Domain)

```php
// {APP_ROOT}/tests/Unit/Domain/Order/OrderTest.php
use App\Domain\Order\{Order, OrderStatus, CustomerId};

it('creates an order in draft status', function () {
    $order = Order::create(new CustomerId('c-1'));
    expect($order->status())->toBe(OrderStatus::Draft);
});
```

## PHPUnit Examples

### Unit (Domain)

```php
// {APP_ROOT}/tests/Unit/Domain/Order/OrderTest.php
namespace Tests\Unit\Domain\Order;

use PHPUnit\Framework\TestCase;
use App\Domain\Order\{Order, OrderStatus, CustomerId};
use PHPUnit\Framework\Attributes\Test;

final class OrderTest extends TestCase
{
    #[Test]
    public function it_creates_an_order_in_draft_status(): void
    {
        $order = Order::create(new CustomerId('c-1'));
        $this->assertEquals(OrderStatus::Draft, $order->status());
    }
}
```

No `RefreshDatabase`, no `TestCase` — pure PHPUnit/Pest with the Domain class only.

### Feature (HTTP)

```php
// {APP_ROOT}/tests/Feature/Order/CreateOrderTest.php
uses(Tests\TestCase::class, RefreshDatabase::class);

it('creates an order', function () {
    Event::fake();
    $customer = Customer::factory()->create();
    $token = $customer->createToken('test', ['orders:write']);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'items' => [['sku' => 'SKU-1', 'qty' => 2, 'price_cents' => 999]],
        ]);

    $response->assertCreated()->assertJsonPath('data.id', fn ($v) => is_string($v));
    Event::assertDispatched(OrderCreated::class);
    expect(OrderModel::count())->toBe(1);
});

it('rejects unauthenticated requests', function () {
    $this->postJson('/api/v1/orders', [])->assertUnauthorized();
});

it('returns 422 on missing items', function () {
    $customer = Customer::factory()->create();
    $this->actingAs($customer)
        ->postJson('/api/v1/orders', ['customer_id' => $customer->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});
```

### Integration (Repository)

```php
// {APP_ROOT}/tests/Integration/Infrastructure/Order/EloquentOrderRepositoryTest.php
uses(Tests\TestCase::class, RefreshDatabase::class);

it('round-trips an order', function () {
    $repo = app(OrderRepository::class);
    $order = Order::create(new CustomerId('c-1'));
    $order->addItem(new Sku('S-1'), 1, new Money(500, 'USD'));
    $repo->save($order);

    $loaded = $repo->findById($order->id);
    expect($loaded)->not->toBeNull();
    expect($loaded->status())->toBe(OrderStatus::Draft);
});
```

### Architecture

```php
// {APP_ROOT}/tests/Architecture/LayersTest.php
arch('domain has no framework imports')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'Symfony', 'Eloquent']);

arch('controllers are invokable or thin')
    ->expect('App\Interface\Http')
    ->toBeClasses();

arch('actions are readonly')
    ->expect('App\Application')
    ->classes()
    ->toBeReadonly();
```

---

## Factories

Use Laravel factories for any test that needs DB rows. Define `state` methods for variants.

```php
final class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::Draft,
            'total_cents' => 0,
        ];
    }

    public function paid(): self
    {
        return $this->state(['status' => OrderStatus::Paid, 'total_cents' => 1999]);
    }
}
```

---

## Determinism

- Freeze time: `Carbon::setTestNow('2026-01-01')` or `Pest\Time::freeze()`.
- Fake side effects: `Queue::fake()`, `Event::fake()`, `Bus::fake()`, `Mail::fake()`, `Notification::fake()`, `Storage::fake()`, `Http::fake()`.
- Seed random sources: `fake()->seed(1234)` if randomness affects assertions.
- One assertion topic per test. Multiple assertions per test are fine; multiple unrelated scenarios are not.

---

## Coverage

- Aim for high coverage on Domain (close to 100%; it is pure code).
- Application: every Action has at least one happy path and one failure path.
- Infrastructure: integration tests for non-trivial mappers and repository methods.
- Do not chase coverage on Eloquent models, controllers (covered by feature tests), or framework glue.

---

## What Not to Mock

- Domain entities — use the real class.
- Eloquent models in feature tests — use the real DB (transactions roll back).
- Time, queues, mail, events, storage — use Laravel's fakes; they are explicit and assertable.

## What to Mock

- Outbound HTTP — `Http::fake([...])`.
- Third-party SDKs — bind a fake implementation in the test container.
- Slow or flaky external services in integration tests; use real services in dedicated end-to-end suites.
# Security

Defense in depth. Validate, authorize, escape, log without leaking.

---

## Input

- Never trust the request. Use Form Request or DTO validation.
- Use parameter binding in every database query. Never concatenate.
- Avoid `Model::create($request->all())` — go through a DTO.
- Reject unknown fields explicitly when the API is supposed to be strict.

## Authentication

- Sanctum for first-party SPA / mobile.
- Passport for third-party OAuth flows.
- 2FA for admin and high-risk accounts.
- Password rules: at least 12 chars, breach check against haveibeenpwned (Laravel `Password::min(12)->letters()->numbers()->symbols()->uncompromised()`).
- Hash with Bcrypt or Argon2id (Laravel default). Never roll your own.

## Authorization

Every write endpoint runs through a Policy. Reads enforce row-level access. Use `spatie/laravel-permission` for roles/permissions storage.

## Mass Assignment

- Explicit `$fillable` lists, or `$guarded = []` with DTO boundary.
- Treat `Model::create([...])` with a DTO-built array as the only write path.

## SQL Injection

- Eloquent / Query Builder bind by default.
- Raw queries (`DB::raw`, `whereRaw`) take **parameters as bindings**, never string interpolation.

```php
// Bad
DB::select("SELECT * FROM orders WHERE status = '$status'");

// Good
DB::select('SELECT * FROM orders WHERE status = ?', [$status]);
```

## XSS

- Blade `{{ }}` escapes by default. Use `{!! !!}` only for trusted, sanitized HTML.
- Sanitize user-provided HTML with a library (`mews/purifier` or similar).
- Set `Content-Security-Policy` header globally.

## CSRF

- Web routes: VerifyCsrfToken middleware enabled (default).
- API routes: stateless tokens via Sanctum / Passport. Do not use cookie auth across origins without `SameSite` and CSRF protection.

## File Uploads

- Validate MIME type (`mimes:jpg,png`) and re-derive type from content, not extension.
- Cap file size (`max:5120` for 5 MB).
- Store outside the web root or in object storage (S3) with private ACL.
- Generate filenames; never trust user-supplied names.

## Secrets

- Read from `env()` only inside `{APP_ROOT}/config/*.php`. Read `config('...')` everywhere else.
- Never commit secrets. `{APP_ROOT}/.env` is gitignored; `{APP_ROOT}/.env.example` is not.
- Rotate secrets on personnel changes and on any leak.
- Vault, Doppler, or AWS Secrets Manager for production.

## Logging

Never log:

- Passwords, tokens, secrets, API keys
- Full credit card numbers (PCI scope)
- Government IDs unless explicitly required and access-controlled
- PII without need

Use Laravel's built-in `LogProcessor` to redact known sensitive fields before write.

## HTTPS and Headers

- HTTPS-only. HSTS with `includeSubDomains; preload`.
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY` or CSP `frame-ancestors`
- `Referrer-Policy: strict-origin-when-cross-origin`

## Dependencies

- `composer audit` in CI.
- Dependabot or Renovate for updates.
- Pin versions; review changelogs before bumping major/minor.

## Multi-Tenancy

- Scope queries by tenant via global scopes.
- Test tenant isolation with explicit cross-tenant assertions in feature tests.
- Never allow user-supplied `tenant_id` from the request body to override the authenticated tenant.

## Rate Limiting and Throttling

- Throttle login, password reset, signup endpoints aggressively.
- Differentiate per IP and per user.
- Slow down (delay) before locking out — better UX, equivalent protection.

## Webhooks

- Verify HMAC signatures.
- Use idempotency keys.
- Reject requests older than 5 minutes (`X-Timestamp` + signed payload).
# Self-Review Checklist

<!-- Architecture rules below are derived from .claude/rules/layers_context.md (source of truth).
     Update that file first, then mirror any changes here. -->

Before returning code to the user, run this checklist. Report at most 5 issues, or `OK`. Loop fix → review at most 3 times.

---

## Architecture

- [ ] Dependencies flow UI → Application → Domain. Infrastructure implements interfaces.
- [ ] Domain code does not import `Illuminate`, `Eloquent`, `Http`, `DB`, facades.
- [ ] Application code does not import `Request`, Eloquent models, or Blade.
- [ ] Controllers are thin: validate → DTO → Action → response.
- [ ] No God service. Each Action has one responsibility.

## DDD (Complex tier only)

- [ ] Entities enforce invariants in their constructor and methods.
- [ ] No public mutable state on entities.
- [ ] Value objects are immutable; equality by value.
- [ ] Domain events are past tense, carry ids, are dispatched after commit.
- [ ] Repository interface is narrow and aggregate-scoped.

## Repositories

- [ ] No business logic.
- [ ] Returns Domain objects or DTOs, not Eloquent models.
- [ ] Read repositories used for complex queries; write repository stays narrow.

## Eloquent

- [ ] No N+1 (eager-loaded relations).
- [ ] No lazy loading inside loops.
- [ ] Pagination on collection queries.
- [ ] Explicit column lists on hot paths.
- [ ] No `findAll()` on large tables.

## Performance

- [ ] Heavy work pushed to queues.
- [ ] External calls have timeout, retries, backoff.
- [ ] Bulk operations use `chunkById` or `lazy`.
- [ ] No unbounded collections returned to the caller.

## API

- [ ] Versioned endpoint (`/api/v1/...`).
- [ ] Consistent response envelope.
- [ ] Proper HTTP status code (201 for create, 422 for validation, 409 for conflict).
- [ ] No Eloquent model returned directly; wrapped in Resource or DTO.
- [ ] Idempotency-Key supported on critical writes.

## Validation and Auth

- [ ] Form Request handles validation **and** authorization.
- [ ] Policies cover per-instance authorization.
- [ ] No mass assignment from `$request->all()`.

## Concurrency

- [ ] Writes are safe under concurrent requests (optimistic lock, unique constraint, advisory lock).
- [ ] Idempotent commands where the operation could be retried.
- [ ] Domain events dispatched with `afterCommit`.

## Security

- [ ] Input validated.
- [ ] SQL bound, never concatenated.
- [ ] Secrets via `config()`, not hardcoded.
- [ ] No PII / passwords / tokens in logs.

## Tests

- [ ] Happy path covered.
- [ ] At least one failure path covered.
- [ ] No `sleep()` in tests; use fakes.
- [ ] No mocks of Domain or DB.
- [ ] Architecture tests still pass.

## Code Quality

- [ ] No dead code, no commented-out blocks.
- [ ] No `TODO` left for the reviewer.
- [ ] Names express intent (no `Manager`, `Helper`, `Util`).
- [ ] Methods read top-down; small (< 30 lines is a good default).
- [ ] PHPStan-clean at the project's level.

## Diff Discipline

- [ ] No drive-by formatting changes.
- [ ] No unrelated refactors mixed with a fix.
- [ ] Tests updated alongside the behavior change.

## Final

If a better architectural approach exists and the cost of switching is small, prefer it. If switching is large, document the trade-off in the response and proceed.

Always prioritize: **maintainability → testability → scalability → ergonomic shortcuts**.
