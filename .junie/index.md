# Rule Catalog

Entry point: `.junie/guidelines.md`.

Load the files below when relevant to the task at hand.
Minimum set for any task: `architecture`, `layers_context`, plus the layer you are touching.

---

## Workflow, Architecture, Output Format, Templates

- `rules/architecture.md` — modes (FEATURE / FIX / REFACTOR / TEST), pipelines, output format, project structure, module generation, code templates, complexity decision heuristic

## Technical Stack

- `rules/technical_stack.md` — API design, database, migrations, Eloquent, performance, caching, queues, jobs, events, repositories, authorization, validation, naming conventions, anti-patterns, Laravel conventions, stack versions

## Domain Layer

- `rules/domain.md` — entities, value objects, aggregate roots, domain events, repository interfaces, invariants

## Application Layer

- `rules/application.md` — actions, commands/queries, DTOs, transaction rules, return types

## Quality & Security

- `rules/quality_gate.md` — testing (Pest / PHPUnit examples, factories, determinism), security (input, auth, secrets, logging), self-review checklist

## Layer Context (per-layer cheat sheets)

- `rules/layers_context.md` — Domain, Application, Infrastructure, Interface/UI hard rules and forbidden patterns

## Advanced Patterns

- `rules/advanced_patterns.md` — concurrency (optimistic/pessimistic locks, advisory locks), idempotency, transactional outbox, CQRS, resilience (timeouts, retries, circuit breaker)
