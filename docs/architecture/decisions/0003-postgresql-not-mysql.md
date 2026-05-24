# ADR 0003 — PostgreSQL 16 (not MySQL) as the system of record

- Status: Accepted
- Date: 2026-05-21
- Deciders: kritskiyp@gmail.com

## Context

The original request named MySQL as the database. The platform's workload pushes back on that choice:

- **Sequential nonce allocation** for outbound EVM/Tron transactions requires a coordination primitive that survives across application instances and is released on transaction end.
- **Outbox-pattern payloads** are best stored and queried as semi-structured JSON.
- **Financial timestamps** must be timezone-aware and monotonically interpretable (Postgres `timestamptz` is unambiguous; MySQL `timestamp` requires care + UTC conventions on every connection).
- **Hot-set filtering** (e.g. `WHERE status='detected'`) benefits from partial indexes — the rest of the table can stay un-indexed and cheap to write.
- **Range-correctness constraints** (no overlapping intervals on derivation paths, no contradictory ledger entries) are best expressed with exclusion constraints.

These exist as workarounds in MySQL 8, but the workarounds are real lines of code and real failure modes.

## Decision

PostgreSQL 16 is the canonical database. The project CLAUDE.md preference (`PostgreSQL preferred. MySQL acceptable.`) confirmed the choice — user approved overriding the original MySQL request after we surfaced the trade-offs.

## What we get out of the box

| Feature | Use |
|---|---|
| `pg_advisory_xact_lock(crc32(...))` | `NonceAllocator` per `(chain, hot_address)`; cron-job exclusion; reorg lock |
| `jsonb` + GIN indexes | `outbox_messages.payload`, `chains.finality_policy`, audit events |
| `timestamptz` | All time-of-event columns; no UTC-conversion footguns |
| Partial indexes (`WHERE status = 'detected'`) | Hot scanning queries; small indexes on big tables |
| Exclusion constraints | `EXCLUDE USING gist (...)` for non-overlapping derivation path ranges |
| Transactional DDL | Migrations either succeed entirely or leave nothing behind |
| `LISTEN / NOTIFY` | Optional cheap pub/sub; we keep Redis but PG gives us a fallback |
| `NUMERIC(40,0)` | wei / satoshi / sun without any float exposure |

## Consequences

### Positive

- Less workaround code. `Cache::lock()` reserved for cross-process distributed locks (e.g. a Horizon-distributed cron); same-DB coordination uses native advisory locks.
- Better fit for financial / time-series data.
- Free read replicas in cloud Postgres offerings; logical replication for downstreams.

### Negative

- One less developer in the team will be fluent in pgsql vs mysql; we accept a small onboarding tax.
- `pgsql` PHP extension required in the container (already in our `Dockerfile`).
- SQLite (used as Laravel's default test DB) is not feature-equivalent. Integration tests must run against a real Postgres in CI. Unit tests stay in-memory / mock-free Domain only.

### Neutral

- Migrations live in Laravel's standard `database/migrations/` plus per-module `Modules/{Ctx}/Infrastructure/Persistence/migrations/` (registered via service provider). All written in schema builder; raw SQL only when we need pgsql-specific syntax (with a `// pgsql:` comment).
