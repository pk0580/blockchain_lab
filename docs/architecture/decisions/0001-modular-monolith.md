# ADR 0001 — Modular monolith with one extracted signing service

- Status: Accepted
- Date: 2026-05-21
- Deciders: kritskiyp@gmail.com

## Context

We are building a payment-infrastructure platform that is also a learning tool. We need 13 bounded contexts (Network, Address, BlockIngestion, ReorgDetection, Transaction, Confirmation, Ledger, Wallet, Withdrawal, Fee, NodeHealth, Webhook, Education) that share a database and need to coordinate around blockchain events.

Two extremes are available:

- **Full microservices** — every context is its own service, communicating via brokers. Maximum isolation, maximum operational cost. Crippling for a solo / small-team project, hides the actual learning material under DevOps noise.
- **Single Laravel monolith with no enforced boundaries** — minimum cost, but the 13 contexts will rot into one anaemic god-namespace within months. The codebase stops being teachable.

A third option fits: modular monolith with one extracted service.

## Decision

- One Laravel 13 application under `src/`, deployed as a single artefact.
- Module-First DDD layout: `src/app/Modules/{Context}/{Domain,Application,Infrastructure,UI}`.
- Module boundaries enforced by Pest `arch()` tests (`tests/Architecture/ModuleBoundariesTest.php`). Cross-module communication via published events and Application-layer Actions only. A `Modules/Foo/Domain` import of `Modules/Bar` fails CI.
- One extracted service: **Go signing service** (separate ADR 0002), because it has a hard security boundary that a same-process module cannot enforce — private keys.

## Consequences

### Positive

- One repo, one deploy, one database transaction boundary for related writes.
- Module boundaries are checked mechanically, not by code-review discipline.
- Any module can be lifted into its own service later: dependencies already point inward (Domain has no Illuminate / no other module).
- Education layer can directly link to platform classes from lessons — no service hopping for the reader.

### Negative

- We share a single PostgreSQL — a runaway query in one module can affect another. Mitigation: per-chain queues, read replicas (later), per-module migrations under `Modules/{Ctx}/Infrastructure/Persistence/migrations`.
- Horizontal scaling is coarser than per-service: we scale Horizon workers per queue, not per module.

### Neutral

- Adding a new bounded context = a new `Modules/{Ctx}/` directory + an entry in `ModuleBoundariesTest::$modules`. No new service required.
