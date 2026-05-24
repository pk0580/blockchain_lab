# Testing

Detect testing framework (Pest or PHPUnit) before writing tests. All business logic is testable; if it is not, refactor.

---

## Detection

- **Pest:** Search for `pestphp/pest` in `{APP_ROOT}/composer.json` or check for `{APP_ROOT}/tests/Pest.php`.
- **PHPUnit:** Default if Pest is not found. Use PHPUnit 12 attributes (`#[Test]`, `#[DataProvider]`).

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
use App\Domain\Order\Entity\Order;
use App\Domain\Order\ValueObject\{OrderStatus, CustomerId};
use App\Domain\Order\Exception\InvalidOrderStatusException;

it('creates an order in draft status', function () {
    $order = Order::create(new CustomerId('c-1'));
    expect($order->status())->toBe(OrderStatus::Draft);
});

it('cannot pay a draft order', function () {
    $order = Order::create(new CustomerId('c-1'));
    expect(fn () => $order->markAsPaid())
        ->toThrow(InvalidOrderStatusException::class);
});
```

No `RefreshDatabase`, no `TestCase` — pure PHPUnit/Pest with the Domain class only.

## PHPUnit Examples

### Unit (Domain)

```php
// {APP_ROOT}/tests/Unit/Domain/Order/OrderTest.php
namespace Tests\Unit\Domain\Order;

use PHPUnit\Framework\TestCase;
use App\Domain\Order\Entity\Order;
use App\Domain\Order\ValueObject\{OrderStatus, CustomerId};
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

No `RefreshDatabase`, no Laravel `TestCase` — pure PHPUnit with the Domain class only.

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
    ->not->toUse(['Illuminate', 'Symfony', 'Eloquent', 'Carbon\Carbon']);

arch('application has no http or eloquent imports')
    ->expect('App\Application')
    ->not->toUse([
        'Illuminate\Http',
        'Illuminate\Database\Eloquent',
        'Illuminate\Support\Facades\Auth',
    ]);

arch('controllers are invokable or thin')
    ->expect('App\Interface\Http')
    ->toBeClasses();

arch('actions are readonly')
    ->expect('App\Application')
    ->classes()
    ->toBeReadonly();

arch('value objects are readonly')
    ->expect('App\Domain')
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
# Authorization

Authorize before validating. Check at the HTTP boundary, and again at the Domain boundary if the rule is a business invariant.

---

## Policies

One Policy per aggregate root. Register automatically or in `AuthServiceProvider::$policies`.

```php
final class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->customerId->value
            || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->isVerified();
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $order->status()->canCancel();
    }
}
```

- Return `bool` for simple allow/deny; return `Response::deny('reason')` when the caller benefits from a message.
- Keep policies free of database queries — the Domain entity is passed in with everything needed.

## Gates

Use Gates for cross-aggregate or global checks (`admin-panel-access`). Define in a service provider.

## Form Request `authorize()`

Call the policy early:

```php
public function authorize(): bool
{
    return $this->user()?->can('create', Order::class) ?? false;
}
```

- `authorize()` returning `false` yields a 403 before rules run.
- For per-instance checks: `$this->user()->can('cancel', $order)` — resolve the model first, then check.

## Authorization in Controllers

Only if you skipped Form Request authorize (e.g., no request body). Use `$this->authorize('view', $order)` inside the controller method.

## Authorization in Actions

Avoid reading `Auth::user()` inside an Application Action. Pass the acting user as part of the DTO or command:

```php
final readonly class CancelOrderData
{
    public function __construct(
        public string $orderId,
        public string $actorId,
    ) {}
}
```

The Action can verify that the actor is permitted, but the **decision** still belongs to the Domain (`Order::cancelBy(UserId $actor)`) when the rule is a business rule.

## Roles and Permissions

- `spatie/laravel-permission` for role/permission storage.
- Roles → Permissions → Policies. Policies are where the `if` lives; roles just group permissions.
- Do not check role names in business logic (`if ($user->hasRole('admin'))`). Check permissions (`$user->can('orders.refund')`).

## API Scopes

- Sanctum tokens carry ability scopes: `['orders:read', 'orders:write']`.
- Check with `$user->tokenCan('orders:write')` in middleware or Form Request.

## Layered Defense

1. Route middleware (`auth:sanctum`, `verified`, `throttle`).
2. Form Request `authorize()` (who can invoke this endpoint).
3. Policy (who can act on this resource).
4. Domain invariant (can this action happen at all given current state).

Each layer catches a different failure mode. Skipping any of them usually means the rule is expressed in the wrong place.
# Validation

Two layers: HTTP shape (Form Request) and Domain invariants (entity constructors).

---

## Form Request

Validates the HTTP shape. Rejects requests with 422 before any Action runs.

```php
final class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_id'        => ['required', 'uuid', 'exists:customers,id'],
            'items'              => ['required', 'array', 'min:1', 'max:100'],
            'items.*.sku'        => ['required', 'string', 'regex:/^[A-Z0-9-]+$/'],
            'items.*.qty'        => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.price_cents'=> ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.min' => 'An order must contain at least one item.',
        ];
    }
}
```

Rules:

- Put authorization in `authorize()`. If the check fails there, Laravel returns 403 before validation runs, so non-authorized users do not get 422.
- Validate every field. Use `prohibited` or `Rule::excludeIf` to block unexpected fields explicitly.
- Use `Rule::unique(...)->ignore(...)` for updates.
- `exists` checks are for referential integrity, not auth — do not use them to hide not-found vs forbidden.

## Domain Validation

The entity constructor and methods enforce invariants that must hold regardless of how the data arrived. See `rules/domain.md`.

```php
new Email($value);              // throws on bad format
$order->markAsPaid();           // throws if status is wrong
```

Domain exceptions map to 4xx/5xx at the HTTP boundary via a global handler (`bootstrap/app.php` exception map).

## DTO Validation

If using `spatie/laravel-data`, attribute-based rules on the DTO merge nicely with Form Request rules:

```php
final readonly class CreateOrderData extends Data
{
    public function __construct(
        #[Uuid, Exists('customers', 'id')]
        public string $customerId,
        /** @var DataCollection<int, CreateOrderItemData> */
        #[DataCollectionOf(CreateOrderItemData::class)]
        public array $items,
    ) {}
}
```

Choose one approach per project. Do not validate the same thing in both places.

## Error Response Shape

422 body:

```json
{
  "message": "The items must contain at least 1 items.",
  "errors": {
    "items": ["The items must contain at least 1 items."]
  }
}
```

Custom exceptions (business failures) map to 4xx with a stable error code:

```json
{
  "error": {
    "code": "invalid_order_status",
    "message": "Only confirmed orders can be paid."
  }
}
```

## What Not to Do

- Inline validation inside controllers (`if (!$request->input('...')) ...`). Use Form Requests.
- `Validator::make(...)` inside an Action. If an Action needs validation, its caller should have run a Form Request or DTO.
- Domain objects silently accepting invalid input. Throw, loudly.
# Anti-Patterns

If you catch yourself writing any of these, stop and refactor.

---

## Structural

- **Fat controller** — business logic inside controller methods. Move to an Action.
- **Fat model** — Eloquent model with 20+ methods and imports from half the codebase. Push behavior into Domain entities; keep the model as a mapping shape.
- **God service** — `OrderService::class` with `createOrder`, `cancelOrder`, `refundOrder`, `exportOrders`, `archiveOrder`. Split into Actions.
- **Fat repository** — `findByX`, `findByY`, `findByXAndY`, `findByEverything`. Extract a read repository with specific-purpose queries or use query objects.
- **Helper static classes** — `OrderHelper::calculate(...)`. If it has logic, it is a service or a domain method. If it has no logic, delete it.
- **Mixed layers** — Domain importing `Illuminate`, Infrastructure leaking Eloquent models to Controllers, Application reaching into Blade.

## Behavioral

- **Hidden side effects** — a getter that mutates, a query that dispatches an event, a `toArray()` that hits the database.
- **Anemic domain model** — entity with public setters and no behavior. Either add behavior or demote it to a DTO.
- **Setter-based state transitions** — `$order->status = 'paid'`. Replace with `$order->markAsPaid()` that enforces invariants.
- **Boolean flags as parameters** — `process($order, true, false, true)`. Split into two methods or pass an intent enum.
- **Primitive obsession** — `string $email` everywhere. Wrap in `Email` VO if the string carries rules.

## Framework Misuse

- **Facades inside Domain / Application** — `Cache::get(...)`, `DB::table(...)`, `Auth::user()` must not appear outside UI or Infrastructure.
- **`request()` / `auth()` helpers outside UI** — only controllers and Form Requests may read the request.
- **Business logic in service providers** — providers only bind and configure.
- **Service locator (`app()` / `resolve()`)** — use constructor injection.
- **Eloquent observers for domain events** — observers are fine for framework concerns (logging, cache invalidation). Business rules belong in Domain events raised explicitly from Actions or entities.
- **Mass-assignment without a DTO layer** — never do `Model::create($request->all())`.

## Queries

- **N+1** — accessing a relation in a loop without eager loading. Use `with()`, `load()`, or a `HasMany` constraint.
- **`findAll()` on large tables** — always paginate or chunk.
- **`SELECT *` on wide tables** — list the needed columns; DTO projection for lists.
- **Queries in views** — Blade or Livewire templates must not touch the database.

## Testing

- **Mocking the Domain** — if you are mocking `Order`, the test is asking the wrong question. Instantiate the real entity.
- **Mocking the database** — integration tests hit the real database (SQLite memory or a test PostgreSQL).
- **Shared state between tests** — each test starts with a clean slate (`RefreshDatabase` or transactions).
- **Assertions on side effects via `sleep()`** — use `Bus::fake()`, `Queue::fake()`, `Event::fake()`, `Notification::fake()`.

## Git Hygiene (applies to diffs)

- Unrelated formatting changes mixed with a logic fix.
- "Cleanup" commits that silently change behavior.
- Removing failing tests instead of fixing them.
# Naming

Names should read as intent. A reader who has never seen the file should understand what a class does from its name alone.

---

## Classes

| Kind | Pattern | Example |
|---|---|---|
| Controller (invokable, one action) | `VerbNounController` | `CreateOrderController` |
| Controller (resourceful, CRUD) | `NounController` | `OrderController` |
| Action / Use Case | `VerbNounAction` | `CreateOrderAction`, `CancelSubscriptionAction` |
| Command (intent object) | `VerbNounCommand` | `CreateOrderCommand` |
| Query | `GetNounQuery`, `FindNounQuery`, `ListNounQuery` | `GetOrderQuery` |
| Query Handler | `<Query>Handler` | `GetOrderHandler` |
| DTO (generic) | `NounData` | `CreateOrderData` |
| Read-side DTO | `NounView`, `NounDto` | `OrderView` |
| Form Request | `VerbNounRequest` | `CreateOrderRequest` |
| API Resource | `NounResource` | `OrderResource` |
| Entity (Domain) | `Noun` | `Order` |
| Value Object | `Noun` | `Money`, `Email`, `OrderId` |
| Repository interface (Domain) | `NounRepository` | `OrderRepository` |
| Repository impl (Infrastructure) | `Eloquent<Noun>Repository` | `EloquentOrderRepository` |
| Eloquent model | `NounModel` if conflicting with entity; else `Noun` | `OrderModel` |
| Event (Domain or framework) | `NounPastTense` | `OrderPaid`, `SubscriptionCancelled` |
| Listener | `VerbNounOn<Event>` | `SendReceiptOnOrderPaid` |
| Job | `VerbNounJob` | `ProcessOrderPaymentJob` |
| Policy | `NounPolicy` | `OrderPolicy` |
| Exception | `<What>Exception` | `OrderNotFoundException`, `InvalidOrderStatusException` |
| Enum | `Noun` or `NounType` | `OrderStatus`, `PaymentMethod` |

## Methods

- Entity behavior reads as a business verb: `markAsPaid()`, `cancel()`, `addItem(Item $item)`.
- Query methods on repositories: `findById()`, `findByEmail()`, `getForDashboard()`.
- Actions have one public entry point: `handle(Data $data)` or `__invoke(Data $data)` (pick one style per project).
- Boolean methods: `isPaid()`, `canBeCancelled()`, `hasItems()`. Do not prefix with `get`.

## Variables

- Collections named plurally: `$orders`, `$items`.
- Single items named singularly: `$order`, `$item`.
- Counters: `$count`, `$total`, `$index`.
- Ids: `$orderId`, not `$id` unless context is unambiguous.

## Banned Suffixes

- `Service`, `Manager`, `Helper`, `Util`, `Utils`, `Controller` (for non-HTTP classes), `Handler` (except Query/Command handler), `Processor`

If legacy code uses them, continue the convention within that area but do not introduce new classes with these names.

## Files

One class per file. File name matches class name exactly. PSR-4 autoload path matches namespace.
# Services, Actions, Use Cases

Avoid generic services. Actions and use cases scale better.

---

## Why Not `OrderService`?

A `UserService` grows into `register`, `update`, `suspend`, `changePassword`, `verifyEmail`, `resendWelcome`, `archive`, `restore`, `exportCsv`. After a year it has 40 methods and five reasons to change.

The industry has moved toward **single-operation classes**. Laravel calls them Actions. DDD calls them Use Cases or Command Handlers. Same shape:

- One class, one operation.
- One public method.
- Intent in the class name: `RegisterUserAction`, `SuspendAccountAction`.
- Composable: one Action can call another.

---

## Action vs Service — When Each Is Acceptable

| Prefer Action when | Prefer Service when |
|---|---|
| Use case is a single transaction (`CancelOrder`) | Library-style utility with multiple pure helpers (`Currency::format`) |
| Must be testable in isolation | Stateless formatter or mapper |
| Triggers side effects (events, jobs, mail) | Pure calculation with no I/O |

"Services" that are really stateless libraries (`MoneyFormatter`, `SlugGenerator`) are fine — they just should not contain use cases.

## Command / Handler

Larger systems formalize the pattern:

```php
final readonly class CreateOrderCommand
{
    public function __construct(
        public string $customerId,
        public array $items,   // list<CreateOrderItemCommand>
    ) {}
}

final readonly class CreateOrderHandler
{
    public function __construct(private OrderRepository $orders) {}

    public function handle(CreateOrderCommand $command): OrderId
    {
        // ...
    }
}
```

Adopt Command/Handler when you want:

- A bus that dispatches through middleware (logging, auth, retries).
- Serializable commands (async, outbox pattern).
- A strict distinction between reads (Query/Handler) and writes.

Otherwise, Action + DTO is lighter and equally clear.

---

## Composition

```php
final readonly class RegisterUserAction
{
    public function __construct(
        private CreateUserAction $createUser,
        private SendWelcomeEmailAction $sendWelcome,
    ) {}

    public function handle(RegisterUserData $data): User
    {
        $user = $this->createUser->handle($data);
        $this->sendWelcome->handle(new SendWelcomeEmailData($user->id));
        return $user;
    }
}
```

Actions composing Actions is fine. Avoid a root Action that orchestrates ten sub-Actions — that is a workflow, consider Saga or a dedicated Application service (still named with intent: `UserOnboardingWorkflow`).

## Where Actions Live

- Simple tier: typically no Action (controller calls Eloquent directly).
- Medium / Complex tier: `{APP_ROOT}/app/Application/{Ctx}/UseCase/{Verb}{Noun}/{Verb}{Noun}Action.php` with the matching `{Verb}{Noun}Data.php` DTO co-located in the same folder.
- Module-first variant: `{APP_ROOT}/app/Modules/{Ctx}/Application/UseCase/{Verb}{Noun}/...`

## Banned

- `UserService`, `OrderService`, `PaymentService`, `Manager`, `Helper`, `Util`.
- Grouping operations by entity instead of by intent.
- Static methods holding state.
