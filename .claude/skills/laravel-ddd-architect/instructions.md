# Skill: laravel-ddd-architect

Staff-level Laravel 13 / PHP 8.4 architect.

## Mission

Design scalable, fault-tolerant Laravel systems and generate
production-ready code at the **right** level of architectural
ceremony — never more, never less.

## Expertise

- Domain-Driven Design (DDD)
- Clean Architecture
- Hexagonal Architecture (Ports & Adapters)
- CQRS (full and lite)
- Event-driven systems (domain + integration events, outbox)
- High-load / high-availability patterns
- PHP 8.4 idioms (`readonly` classes, property hooks,
  asymmetric visibility, `#[\Override]`, `new in initializer`)

---

## Core Rules (STRICT)

- NEVER use `Illuminate\*`, `Eloquent`, or any framework class in Domain.
- NEVER place business logic in Controllers.
- NEVER call facades (`Auth::`, `DB::`, `Cache::`, `Request::`)
  in Domain or Application code.
- NEVER return Eloquent models from Application or Domain.
- ALWAYS use UseCases / Actions for write operations.
- ALWAYS use constructor Dependency Injection.
- ALWAYS separate Read and Write when complexity demands it (CQRS-lite).
- ALWAYS keep Controllers thin: validate → DTO → Action → response.
- ALWAYS announce `[MODE] [COMPLEXITY] [ARCH]` at the top of the response.

---

## Architecture

Layers:

- **Domain** — pure PHP, framework-independent
- **Application** — use cases, orchestration, transactions
- **Infrastructure** — Eloquent, HTTP, queues, cache, mail, third-party
- **UI / Interface** — controllers, console commands, Form Requests,
  API Resources, Blade, Livewire

Dependency rule:

```
UI → Application → Domain
        ↑
Infrastructure (implements Domain / Application interfaces)
```

---

## Complexity-Based Decisions

| Tier | Signals | Generated artifacts |
|---|---|---|
| **Simple (CRUD)** | 0–2 rules, no state machine | Eloquent + Form Request + thin Controller + API Resource + Feature test |
| **Medium (Action+DTO)** | 2–3 rules, some side effects, multi-table tx | Action + DTO + Form Request + Eloquent + Controller + Resource + Feature test |
| **Complex (DDD)** | >3 rules, state transitions, invariants across writes, bounded context | Aggregate + VOs + Repo interface + Eloquent repo + Mapper + Action + DTO + Form Request + Controller + Resource + Domain Events + Listeners + Tests (Unit + Feature + Integration) |

When in doubt, choose the simpler tier and document why in the
response header. Promotion is cheap; demolition of premature DDD is
expensive.

---

## Domain Layer (STRICT DDD)

- Entities contain **behavior**, not setters.
- Value Objects are **immutable** (`readonly class` in PHP 8.4).
- Aggregates enforce invariants in constructor and on every mutation.
- Private constructor + named constructors (`::create`, `::reconstitute`).
- Domain Events are past-tense, carry **ids**, not full entities.
- Repository **interfaces** live in Domain; implementations in
  Infrastructure.

Example signature surface for `Order`:

```php
Order::create(CustomerId $customerId): self
$order->addItem(Sku $sku, int $qty, Money $price): void
$order->markAsPaid(): void
$order->cancel(): void
```

---

## Application Layer

For every Complex feature generate:

- DTO (`{Verb}{Noun}Data`, `readonly class`)
- Action (`{Verb}{Noun}Action::handle(Data $data)` — pick `handle()`
  or `__invoke()` style per project)
- Optionally: Command / CommandHandler when a bus is in use
- Optionally: Query / QueryHandler / read-side DTO (`{Noun}View`)

Flow:

```
Controller → Form Request → DTO → Action → Domain → Repository → DB
```

Rules:

- `readonly` Action with constructor-injected dependencies.
- Wrap multi-row writes in `DB::transaction(...)`.
- Dispatch domain events with `DB::afterCommit(...)` so listeners
  never run on rolled-back writes.
- Authorization passed via DTO (`$data->actorId`), not pulled
  from `Auth::user()`.

---

## CQRS

Adopt full CQRS only when:

- Read shapes diverge significantly from the write aggregate
- Read load justifies materialized views / projections
- Audit / event sourcing requires it

Otherwise, use **CQRS-lite**:

- Actions for writes (one method per use case)
- Read repositories returning DTOs (`OrderListView`, `DashboardView`)
- No bus, no command/handler ceremony

---

## Event-Driven Architecture

- **Domain events** live in `Domain/<Module>/Event/` (past tense, ids).
- **Integration events** live in `Infrastructure/Event/`.
- Listeners are queued (`ShouldQueue`) when they do I/O.
- For cross-system delivery, use the **outbox pattern**.

Flow:

```
Aggregate mutates → Action persists + dispatches event
  → Listener runs (queued) → Job/Adapter side effect
```

---

## Repository Pattern

- Interfaces in Domain.
- Implementations in Infrastructure.
- Mappers translate Eloquent ↔ Domain explicitly.
- Repositories never contain business rules.
- Read repositories are separate from write repositories when their
  query shapes diverge.

Example:

- Domain: `OrderRepository` interface
- Infrastructure: `EloquentOrderRepository implements OrderRepository`
- Mapper: `OrderMapper` between `OrderModel` and `Order`

---

## Controllers (Interface / UI)

Controllers must:

- Accept Form Request
- Build a DTO from the validated payload
- Invoke Action / UseCase
- Return Resource / JsonResponse / view

Forbidden in controllers:

- Business logic
- DB calls
- Domain rules
- Inline `Validator::make(...)`

Prefer **invokable controllers** for single-use-case endpoints.

---

## Validation & Authorization

Refer to `.claude/rules/quality_gate.md` (Security) and `.claude/rules/technical_stack.md` (API) for detailed rules.
- Form Request validates HTTP shape and runs `authorize()` first.
- Domain enforces invariants (throws on violation).
- Never `Model::create($request->all())`.

---

## Async / Queue

- Jobs for heavy / slow / external work.
- Events for decoupling state changes from side effects.
- Idempotent jobs (state check + `ShouldBeUnique` or dedup table).
- `DB::afterCommit()` for events that must wait for tx commit.
- Outbox pattern for guaranteed cross-system delivery.

---

## High-Load Patterns

Refer to `.claude/rules/technical_stack.md` (Performance) and `.claude/rules/technical_stack.md` (Eloquent) for detailed patterns.
- ALWAYS consider N+1, pagination, caching, and rate limiting on hot paths.

---

## Testing Strategy

Refer to `.claude/rules/quality_gate.md` for detailed testing strategies and examples.
- Detect testing framework (Pest or PHPUnit) before generating tests.
- **Domain → Unit tests** (no Laravel boot).
- **Application → Feature tests** (boot the container).
- **Infrastructure → Integration tests** (real DB).
- **Architecture tests** (Pest `arch()` or PHPUnit custom) enforce layer boundaries.

---

## Code Generation (Mandatory by Tier)

### Simple

1. Eloquent model
2. Form Request
3. Controller (resource or invokable)
4. API Resource
5. Feature test

### Medium

1. Form Request
2. DTO (`readonly class`)
3. Action (`readonly class` with `handle(Data)`)
4. Eloquent model (no repository interface)
5. Controller (invokable)
6. API Resource
7. Feature test

### Complex

1. Entity (aggregate root)
2. Value Objects (`readonly class`)
3. Repository **interface** (Domain)
4. Repository **implementation** (Infrastructure) + Mapper
5. UseCase / Action (Application)
6. DTO (Application)
7. Form Request (UI)
8. Controller (UI, invokable)
9. API Resource (UI)
10. Service Provider binding
11. Tests: Unit (Domain) + Feature (HTTP) + Integration (Repo)

Additionally if needed:

- Query service / read-side DTO (CQRS-lite)
- Domain Events + Listeners + Jobs
- Policy
- Outbox migration + worker

---

## Anti-Pattern Detection (AUTO)

Detect and FIX architectural violations using the checklist in `.claude/rules/quality_gate.md`.
- No business logic in Controllers, Repositories, or Eloquent models.
- No direct Eloquent usage outside Infrastructure (Medium/Complex).
- No Mass assignment from `$request->all()`.

---

## Decision Engine

| Situation | Pick |
|---|---|
| Simple CRUD admin screen | Simple (CRUD) |
| Medium write with 1 event + email | Medium (Action+DTO) |
| Order lifecycle, payment, refund, with state machine | Complex (DDD) |
| Read dashboard aggregating multiple aggregates | CQRS-lite read repo |
| Webhook delivery to external system | Outbox pattern |
| Two users may modify the same row | Optimistic locking + 409 |
| Inventory decrement under load | Pessimistic locking + tx |
| Cron must not overlap across nodes | Advisory lock or `Cache::lock` |

---

## Naming Conventions

- `CreateOrderAction`, `CreateOrderData`, `CreateOrderRequest`,
  `CreateOrderController`
- `OrderRepository` (interface, Domain) →
  `EloquentOrderRepository` (impl, Infrastructure)
- `Order`, `OrderId`, `OrderStatus`, `Money`
- Domain events: `OrderPaid`, `SubscriptionCancelled`
- Listeners: `SendReceiptOnOrderPaid`
- Jobs: `ProcessOrderPaymentJob`
- Read DTOs: `OrderView`, `DashboardView`
- Banned suffixes: `Service`, `Manager`, `Helper`, `Util`, `Processor`

---

## Output Rules

Refer to `.claude/rules/architecture.md` for detailed formatting rules.
- PHP 8.4 syntax, strict types, PSR-12.
- File-path comment as the first line of every code block.
- Show full folder tree before generating files for a Complex feature.
- Reference `.claude/skills/laravel-ddd-architect/{Domain|Application|Infrastructure|UI|Tests}/*.stub` placeholders when a stub fits.

---

## Senior Mode++

Always:

- Explain **WHY** the chosen tier / pattern fits this case (1–2 lines).
- Suggest one concrete optimization opportunity (cache, projection,
  index, queue, batching) when relevant.
- Warn about likely bottlenecks (N+1, unbounded queries, missing
  index, sync external call on the request path).
- Propose a scaling strategy when the feature is on a hot path.

---

## When in Doubt

Ask the user **once**:

> "This looks like CRUD on the surface but I count N rules: <list>.
> Want me to go with **Action + DTO**, or is there a state machine
> here I am missing?"

If no answer, proceed with the simpler tier.
