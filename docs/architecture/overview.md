# Architecture overview

`blockchain-lab` is a teaching-oriented L1/L2 blockchain platform. Production-grade payment infrastructure that doubles as an executable textbook: every covered concept (gas, mempool, confirmations, reorg, raw transactions, signing, private keys) maps to a concrete module in this codebase, and lessons link to those classes.

For the rolling implementation plan see [`/STEPS.md`](../../STEPS.md).
For specific decisions see [`decisions/`](./decisions/).

## Why this shape

A naive monolith hides the boundary between "stuff that touches private keys" and "stuff that orchestrates the world". A microservice-per-context approach buries the learner under operations work. We split the difference:

- **Laravel modular monolith** for orchestration, scanners, withdrawal workers, education UI. One codebase, one deploy, but enforced module boundaries (Pest `arch()` tests). Each `Modules/{Context}` can be extracted later without rewriting business logic.
- **Go signing service** (Phase 2) — the only place where private keys live. Built around `trustwallet/wallet-core` so we get 130+ coin families behind one interface. mTLS, KMS-backed, audit-logged. This is the security boundary; everything else trusts it as a black box.

See `decisions/0001-modular-monolith.md` and `decisions/0002-go-signing-service.md`.

## Layers and dependency direction

```
UI / Interface  ──▶  Application  ──▶  Domain
                            ▲
                      Infrastructure  (implements Domain interfaces)
```

- **Domain** — pure PHP, no Illuminate / Eloquent / Symfony. `DateTimeImmutable` only. Entities, value objects, repository interfaces, domain events, domain exceptions.
- **Application** — Actions (one per use case), DTOs, Query handlers. Wraps multi-row writes in `DB::transaction()`. Dispatches domain events via `DB::afterCommit()`.
- **Infrastructure** — Eloquent models / repositories / mappers, HTTP clients (RPC, signing-svc), queue handlers, third-party SDKs.
- **UI** — Controllers (invokable), Form Requests, API Resources, Policies, Livewire / Inertia components (Phase 8).

Boundaries are enforced by `tests/Architecture/LayersTest.php`. A violation fails CI before review.

## Bounded contexts

13 modules under `src/app/Modules/`. Each is independently testable; each maps to a distinct bounded context with its own ubiquitous language.

| Module | Responsibility | Key invariants |
|---|---|---|
| `Network` | Chain registry, RPC providers, `ChainAdapter` interface | One adapter per family (BTC/EVM/TRON), new L2 = new `ChainSpec` only |
| `Address` | HD-wallet derivation (BIP-44), address validation | Private keys never leave signing-svc; here only `address` + `derivation_path` |
| `BlockIngestion` | Block scanner, head tracking | Blocks processed strictly in height order per chain |
| `ReorgDetection` | Detect reorg, walk to common ancestor | `depth > maxReorgDepth(chain)` → human alert, stop withdrawals |
| `Transaction` | Parse tx, classify in/out, persist | One `(chain_id, tx_hash)` per row, dedup'd |
| `Confirmation` | Count confirmations, detect finality | `confirmations = head - block_height + 1`; recomputed on every head |
| `Ledger` | Double-entry book of all balance changes | Reorg → reversal entries (never DELETE confirmed entries) |
| `Wallet` | User wallets + balance projection | Balance = sum of confirmed ledger entries (read model) |
| `Withdrawal` | Outbound tx orchestration | `(chain_id, hot_address, nonce)` unique; stuck → RBF / resend |
| `Fee` | Fee estimation per chain | BTC `estimatesmartfee` / EVM EIP-1559 / Tron bandwidth&energy |
| `NodeHealth` | RPC probe, circuit breaker, failover | Quorum on critical block fetches (anti-poisoning) |
| `Webhook` | Outbound events to consumers via outbox | At-least-once + HMAC signed + consumer-side idempotency expected |
| `Education` | Lessons (markdown) + interactive playgrounds | Each lesson links to platform classes |

## Cross-cutting patterns

| Concern | Pattern | Reference |
|---|---|---|
| Concurrency on hot writes | Optimistic locking (`version` column) + 409 | `.claude/rules/advanced_patterns.md` |
| Sequential nonce allocation | PG `pg_advisory_xact_lock` + unique `(chain, hot_address, nonce)` | `Modules/Withdrawal` |
| Cross-system event delivery | Transactional outbox | `Modules/Webhook` |
| Critical write retries | `Idempotency-Key` header → `idempotency_keys` table | API middleware |
| RPC reliability | Timeout + retry + circuit breaker per endpoint | `Modules/NodeHealth` |
| Failure isolation | Per-chain Horizon queues (bulkhead) | `Modules/Withdrawal`, `Modules/BlockIngestion` |
| Hot-path reads | Bloom filter on watched addresses + Redis cache | `Modules/BlockIngestion` |

## Data model highlights

PostgreSQL 16. Money in `NUMERIC(40,0)` minor units (wei / satoshi / sun). Never float.

Core tables:

```
chains, chain_rpc_endpoints
blocks (PK chain_id, height), scan_cursors
addresses, hd_seeds (ref to KMS only — no key data)
incoming_transactions (state: detected → confirming → confirmed → finalized | orphaned)
withdrawals (state machine, idempotency_key UNIQUE)
nonce_assignments (PK chain_id, hot_address, nonce)
ledger_entries (double-entry: direction debit|credit, status pending|confirmed|reversed)
outbox_messages (JSONB payload, published_at NULL until delivered)
idempotency_keys (request_hash + cached response, 24h TTL)
```

Full DDL ships with each phase's migrations.

## What the learner gets

Every concept lands at three altitudes:

1. **Lesson** — analogy → protocol → math (under `src/content/lessons/`).
2. **Code** — the running module that implements the concept.
3. **Playground** — interactive surface in the UI that calls the real API (Phase 8). The reorg simulator forces a `bitcoind invalidateblock` in front of the learner so they *see* `confirmed → orphaned → re-detected`.

This is the value proposition: no other repo lets a junior engineer single-step through a real reorg with the source code open beside them.

## Where to start reading

- New to DDD here? → `.claude/rules/layers_context.md`
- Need to add a new L2? → `Modules/Network/Domain/Contract/ChainAdapter.php` (Phase 1)
- Need to understand reorg handling? → `Modules/ReorgDetection` + lesson `02-bitcoin/reorg-and-finality.md`
- Wondering why we chose X? → ADRs in [`decisions/`](./decisions/)
