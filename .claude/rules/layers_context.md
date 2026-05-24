# Context: Application Layer

Applies to files inside `{APP_ROOT}/app/Application/` (or `{APP_ROOT}/app/Modules/*/Application/`).

---

## Hard Rules

- May import from Domain. May import infrastructure interfaces. May not import HTTP, Eloquent, Blade.
- One Action per use case, one public entry method (`handle()` or `__invoke()`).
- `readonly` class with constructor-injected dependencies.
- Wrap multi-row writes in `DB::transaction()`. Dispatch domain events with `afterCommit`.

## Allowed Concepts

- Actions (use cases)
- Commands and Command Handlers (when CQRS is justified)
- Queries and Query Handlers
- DTOs (input and output)
- Application services (rare, when an Action would be too small)

## Required Patterns

- DTO at the boundary; no `Request` objects past the controller.
- Authorization passed in via DTO (`$data->actorId`), not pulled from `Auth::user()`.
- Returns Domain entities, VOs, or DTOs. Never Eloquent.

## Forbidden

- `Request`, `Response`, `Auth::user()`, `request()`, `auth()`.
- Direct Eloquent calls (use repository or inject the model class).
- Business rules that belong in entities (an Action that says `if ($order->status === 'paid')` should call `$order->markAsPaid()` and let the entity decide).

See `.claude/skills/laravel-ddd-architect/instructions.md` for Application layer patterns.
# Context: Domain Layer

Applies to files inside `{APP_ROOT}/app/Domain/` (or `{APP_ROOT}/app/Modules/*/Domain/`).

---

## Hard Rules

- Pure PHP. No `Illuminate`, no `Eloquent`, no `Symfony`, no HTTP, no serialization.
- No facades. No `app()`, `resolve()`, `config()`, `request()`, `auth()`.
- No `DateTime` mutations. Use `DateTimeImmutable` or `Carbon\CarbonImmutable`.
- No `Carbon` (mutable). Prefer `CarbonImmutable` if you must use Carbon at all; ideally vanilla `DateTimeImmutable`.

## Allowed Concepts

- Entities (with identity)
- Value objects (immutable, equality by value)
- Aggregate roots and aggregates
- Domain services (stateless, span entities)
- Domain events (past tense, ids only)
- Repository **interfaces**
- Domain exceptions

## Required Patterns

- Private constructor + named constructor (`::create`, `::reconstitute`).
- Mutating methods read as business intent: `$order->markAsPaid()`, not setters.
- Invariants enforced at construction and on every mutation.
- `final` classes by default.
- `readonly` for value objects.

## Forbidden

- Public mutable properties (except `readonly`).
- Setters for state transitions.
- Returning Eloquent / framework objects.
- `toArray()` for persistence shape (mappers do that in Infrastructure).

## Domain Services

- Stateless operations spanning multiple entities that don't belong on any single one.
- Rare — if you need one, verify the entity isn't anemic.
- Constructor-injected dependencies only. No static methods, no facades.
- Live in `Domain/{Context}/Service/`.

## Domain Exceptions

- Named after the violated invariant: `InvalidOrderStatusException`, `InsufficientInventoryException`.
- Named constructors for common violations: `::cannotPay(OrderStatus $current)`.
- Live in `Domain/{Context}/Exception/`.
- Mapped to HTTP status codes in Infrastructure / `{APP_ROOT}/bootstrap/app.php`, never in Domain.

```php
final class InvalidOrderStatusException extends \DomainException
{
    public static function cannotPay(OrderStatus $current): self
    {
        return new self("Cannot pay an order in status: {$current->value}");
    }
}
```

## Quick Test

If you can run `phpunit --filter Domain` without booting Laravel, the layer is clean.
# Context: Infrastructure Layer

Applies to files inside `{APP_ROOT}/app/Infrastructure/` (or `{APP_ROOT}/app/Modules/*/Infrastructure/`).

---

## Hard Rules

- Implements interfaces declared in Domain or Application.
- May depend on Application and Domain. Must not depend on UI.
- All framework / external integration lives here: Eloquent, Http, Cache, Queue, Mail, third-party SDKs.

## Typical Contents

- Eloquent models (`Persistence/Eloquent/Models/`)
- Repository implementations (`Persistence/Eloquent/Repositories/`)
- Mappers between Eloquent and Domain (`Persistence/Eloquent/Mappers/`)
- HTTP clients (`Http/Clients/`)
- External SDK adapters (`Stripe/`, `S3/`, ...)
- Mail sender adapters
- Queue handlers (or in `Application` if they are the use case itself)

## Required Patterns

- Repository implementations transform Eloquent models into Domain objects via mappers.
- HTTP clients have timeout, retry, circuit breaker (see `.claude/rules/advanced_patterns.md`, Resilience section).
- External adapters wrap third-party exceptions in app-level exceptions.

## Forbidden

- Returning Eloquent models out of repository methods (unless interface explicitly types `Model`).
- Business logic in mappers.
- Side effects in constructors (no DB calls in `__construct`).

See `.claude/rules/technical_stack.md` (Eloquent section) for Eloquent and repository implementation details.
# Context: Interface / UI Layer

Applies to files inside `{APP_ROOT}/app/Interface/Http/` (layer-first layout) or `{APP_ROOT}/app/Modules/{Ctx}/UI/` (module-first layout).

---

## Hard Rules

- Controllers, console commands, Form Requests, API Resources, Blade, Livewire components.
- Depends on Application. May reference Domain only for type hints in DTOs or response mapping.
- Does not contain business logic.

## Controller Responsibilities

1. Validate (delegated to Form Request `rules()`).
2. Authorize (delegated to Form Request `authorize()`).
3. Translate validated input into a DTO.
4. Invoke an Action.
5. Return a Resource, JSON, or view.

## Required Patterns

- Invokable controllers for single-use-case endpoints (`__invoke`).
- Form Request per write endpoint. Never inline `Validator::make()` in controllers.
- API Resources or DTOs for response shape. No raw Eloquent leakage.
- Blade templates do not query the DB.

## Forbidden

- Business rules in controllers.
- `DB::`, `Cache::`, `Mail::` calls inside controllers (that is Infrastructure work, invoked through an Action).
- Returning `Model::find($id)` directly to JSON without a Resource.
- Controllers larger than ~50 lines (split into multiple invokable controllers).

## Console Commands

- Same shape as controllers: parse args, build DTO, call Action.
- Schedule in `routes/console.php`.

## Livewire / Inertia

- Same boundary: components dispatch to Actions; rendering layer has no business rules.
- For Livewire, properties are the DTO; lifecycle methods are thin.

See `.claude/rules/technical_stack.md` (API Design section) for HTTP API design details.
