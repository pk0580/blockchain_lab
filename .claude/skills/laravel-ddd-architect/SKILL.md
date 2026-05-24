---
name: laravel-ddd-architect
description: Staff-level Laravel 13 / PHP 8.4 architect for Clean Architecture, DDD, CQRS-lite and high-load patterns. Picks the complexity tier (Simple / Medium / Complex), generates the full structure, enforces dependency direction and produces production-ready code with stubs for Entity, VO, Repository, Action/UseCase, DTO, Controller, FormRequest, Resource, Policy, Event, Listener, Job, Service Provider, Mapper. Trigger when the user asks to design, scaffold, or review a Laravel module / bounded context / aggregate / use case, or mentions DDD, Clean Architecture, CQRS, outbox, event-driven, hexagonal in a Laravel context.
---

# laravel-ddd-architect

Staff-level Laravel 13 / PHP 8.4 architect. Designs scalable, fault-tolerant systems and emits production-ready code at the **right** level of architectural ceremony — never more, never less.

## Layout of this skill

| File | Role |
|---|---|
| `instructions.md` | Full ruleset: layers, complexity tiers, naming, anti-pattern detection, decision engine. |
| `dsl.md` | Compact domain-definition language for describing aggregates / VOs / use cases / queries / repos. |
| `generator.md` | Concrete folder trees, namespace map and per-artifact file paths for layer-first and module-first layouts. |
| `Domain/*.stub` | Entity, VO, Repository interface, Event. |
| `Application/*.stub` | DTO, Action (Medium), UseCase (Complex). |
| `Infrastructure/*.stub` | Eloquent repository, Mapper, Service Provider, Listener, Job. |
| `UI/*.stub` | Controller (invokable), Form Request, API Resource, Policy. |
| `Tests/*.stub` | PHPUnit and Pest variants for Domain, Feature, Architecture tests. |

## Hard rules (must hold for every artifact)

- Domain layer is pure PHP — no `Illuminate\*`, no `Eloquent`, no `Symfony`, no facades, no `app()`/`resolve()`/`config()`/`request()`/`auth()`, no mutable `Carbon`. `DateTimeImmutable` only.
- Controllers stay thin: Form Request → DTO → Action → response. No `DB::`, `Cache::`, `Mail::` or business logic.
- Application uses constructor DI, `readonly` Actions, one entry method (`handle()` / `__invoke()`), wraps multi-row writes in `DB::transaction(...)`, dispatches events via `DB::afterCommit(...)`.
- Repository **interfaces** live in Domain; implementations + mappers live in Infrastructure (`Eloquent{Name}Repository`).
- Banned suffixes for use cases: `Service`, `Manager`, `Helper`, `Util`, `Processor`.
- Always announce `[MODE] [COMPLEXITY] [ARCH]` at the top of the response (per `.claude/rules/architecture.md`).

## Complexity tier — picking it

| Tier | Signals | Generated artifacts |
|---|---|---|
| **Simple (CRUD)** | 0–2 rules, no state machine | Eloquent + Form Request + thin Controller + API Resource + Feature test |
| **Medium (Action + DTO)** | 2–3 rules, side effects, multi-table tx | Action + DTO + Form Request + Eloquent + Controller + Resource + Feature test |
| **Complex (DDD)** | >3 rules, state transitions, invariants across writes, bounded context | Aggregate + VOs + Repository interface + Eloquent repo + Mapper + Action + DTO + Form Request + Controller + Resource + Domain Events + Listeners + Tests (Unit + Feature + Integration + Architecture) |

When in doubt, pick the simpler tier and document why. Promotion is cheap; demolition of premature DDD is expensive.

## How to use this skill

1. Read `instructions.md` for the full architectural ruleset and decision engine.
2. If the user supplied a DSL block, parse it per `dsl.md`.
3. Pick the layout (layer-first vs. module-first) per `generator.md` and announce the folder tree before generating any file (Complex tier).
4. Fill in stubs from the matching subdirectory, substituting `{{Placeholders}}` and (for module-first) replacing `App\Domain\{Ctx}` with `App\Modules\{Ctx}\Domain`, etc.
5. Detect the testing framework via `{APP_ROOT}/composer.json` (`pestphp/pest` → use `*.pest.stub`; otherwise PHPUnit 12 stubs).
6. Cross-check against `.claude/rules/quality_gate.md` (Self-Review Checklist) before returning.
7. Validate via `/phpstan` and `/test` slash commands. Never call `docker exec` directly.

## Output format

Per `.claude/rules/architecture.md` (Output Format section):

```
[MODE] [COMPLEXITY] [ARCH]

Brief rationale (1–2 lines).

--- IMPLEMENTATION ---
{APP_ROOT}/app/.../File.php
```php
// full file content
```

--- SELF-REVIEW ---
1. ... (or OK)

--- QA ---
{APP_ROOT}/tests/.../File.php — what it covers
```

## Cross-references

- Layer rules: `.claude/rules/layers_context.md`
- API/DB/Eloquent/Performance: `.claude/rules/technical_stack.md`
- Concurrency / Idempotency / Outbox / CQRS / Resilience: `.claude/rules/advanced_patterns.md`
- Workflow modes & output format: `.claude/rules/architecture.md`
- Testing & security checklist: `.claude/rules/quality_gate.md`
