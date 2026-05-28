# blockchain-lab — implementation plan

Учебная L1 / L2 блокчейн-платформа: BTC + Ethereum + Tron + Polygon (L2). Modular monolith на Laravel 13 / PHP 8.4 + изолированный Go signing service на `trustwallet/wallet-core`. Каждый изучаемый концепт (gas, mempool, confirmations, reorg, raw tx, signing, private keys) имеет работающий код + интерактивный playground.

---

## Progress (resume here after a context clear)

> **Current phase:** Phase 8 — Education UI + Playgrounds **DONE** (8.1 + 8.2 + 8.3 + 8.4 + 2 system-design lessons)
> **Last updated:** 2026-05-24
> **Next action if resuming:** Phase 8 closed. 767 PHP tests (+14 vs 8.3), PHPStan L8 clean. Admin dashboard на `/admin` (Inertia + 5s JSON polling) с 5 секциями: node health / mempool / withdrawal queue / ledger / outbox lag. 5 порт-провайдеров в `Education::Application::Contract::Dashboard\*` + Eloquent-реализации с собственными lookup-моделями. 2 урока в `05-system-design/` (multi-sig 2-of-3, why HSM). Phase 9+ (production hardening) — на паузе до решения о mainnet/нагрузке.

- [x] **Phase 0 — Bootstrap.** Docker, Make, Postgres+Redis+Horizon+Bitcoin-regtest compose, stubs under `stubs/`, ADRs 0001/0002/0003, README.
- [x] **Phase 1 — Network + ChainAdapter contract.** 47 files. 377 tests pass, PHPStan L8 clean.
- [x] **Phase 2 — Signing service.** Pure-Go (btcd/btcec + go-ethereum/crypto + bip32+39), ADR 0004. PHP `SigningClient` + `HttpSigningClient`. 381 PHP tests + 4 Go tests pass.
- [x] **Phase 3 — Address + HD wallets.** `HdSeed` + `Address` aggregates, BIP-44 `DerivationPathFactory`, `EloquentAddressRepository::nextDerivationIndex` with PG `pg_advisory_xact_lock`. Live end-to-end: 6 addresses across 3 families with monotonic indexes 0/1. Module boundary update: **`Network` is now formally the platform's shared kernel** (ModuleBoundariesTest exempts it from cross-module-imports rule). 381 PHP tests pass, PHPStan L8 clean.
- [x] **Phase 4 — BlockIngestion + Confirmation.** `BlockIngestion` module: `Block` / `ScanCursor` / `IncomingTransaction` aggregates, `BlockSource` port + `BitcoinCoreBlockSource` polling impl, `ScanNextBlock` + `InitChainCursor` actions, per-chain `ScanChainJob` on `scan.{chain}` queue, `scan:run` / `scan:init` commands. `Confirmation` module: `ConfirmationCalculator` domain service, `UpdateConfirmations` action emitting `TransactionConfirming` / `TransactionConfirmed`, own Eloquent view over the shared `incoming_transactions` table (no cross-module Domain imports). Cross-module address bridge: `AddressDirectory` contract in BlockIngestion::Domain, `RedisAddressDirectory` impl + `RegisterAddressInDirectory` listener in Address::Infrastructure. Real `BitcoinAdapter` replaces `NoOpChainAdapter` for the bitcoin family; EVM/Tron stay NoOp until Phase 4.5/6. 421 PHP tests pass (+40 vs Phase 3), PHPStan L8 clean.
- [x] **Phase 5 — ReorgDetection + Ledger.** `IncomingTxStatus` / `ConfirmationOutcome` enums расширены `Finalized` и `Orphaned`. `ConfirmationCalculator` теперь промотает Confirmed → Finalized по `chain.max_reorg_depth` и эмитит `TransactionFinalized`. `BlockRepository` расширен `findByHash` / `latestForChain`. `App\Modules\ReorgDetection`: `ChainHistory` + `ReorgWriter` Domain ports, `ChainComparator` service (single-tick parent_hash check, итеративный walk-back через rollback курсора), `EvaluateBlockReorgAction` слушает `BlockIngested` через Infrastructure listener, эмитит `ReorgDetected` (+ заглушка `ReorgTooDeep` для Phase 6 alert wiring). `App\Modules\Ledger`: двойная запись `ledger_entries` (миграция 2026_05_24_000001), `LedgerEntry` aggregate с `recordDeposit` / `reverse`, `WalletOwnership` + `ConfirmedTransactionView` Domain ports. `RecordLedgerCreditAction` слушает `TransactionConfirmed` (идемпотентен по `operation_type` + `operation_ref`), `ReverseLedgerForReorgAction` слушает `ReorgDetected` (никогда не удаляет, всегда reversal). Cross-module пользоваться через Infrastructure listener pattern (Domain / Application не импортирует чужие неймспейсы). `ScanNextBlockAction` финальный cursor save сделан conditional, чтобы ReorgDetection rollback переживал тик. Live reorg test в `tests/Feature/Live/` (skipped по умолчанию через `BITCOIN_LIVE_TESTS=1`). 460 PHP tests pass (+39 vs Phase 4), PHPStan L8 clean.
- [x] **Phase 6 — Withdrawal + Fee + Nonce** (sub-phases 6.1 + 6.2 + 6.3 done)
  - **6.1 done.** Fee module + NonceAllocator + migration `nonce_assignments`. 498 PHP tests, PHPStan L8 clean.
  - **6.2 done.** Network contracts расширены (`SigningClient::signRawTx`, `ChainAdapter::broadcast`, VO `SignedRawTx`, `BroadcastFailedException`). `BitcoinAdapter::broadcast` через `sendrawtransaction`. Новый `EvmAdapter` (currentHead/isHealthy/broadcast/supports) на `EvmJsonRpc` JSON-RPC 2.0 в `Network::Infrastructure\Rpc`. `HttpSigningClient::signRawTx` → `/v1/tx/sign`. `Withdrawal::Domain`: aggregate `Withdrawal` со state-машиной (Requested→Built→Signed→Broadcasted; Failed; Confirming/Confirmed/Stuck/Replaced зарезервированы для 6.3), VOs (`WithdrawalId`, `WalletId`, `IdempotencyKey`, `WithdrawalStatus`, `WithdrawalAmount`, `Currency`, `FeeQuoteSnapshot`, `BuiltTransaction`), events (`WithdrawalRequested/Built/Signed/Broadcasted/Failed`), `WithdrawalRepository` + `TxBuilder`/`TxBuilderRegistry` контракты, exceptions. `Withdrawal::Application`: `RequestWithdrawalAction` оркеструет Fee→Nonce(EVM)→Build→Sign→Broadcast тремя пер-шаговыми транзакциями (партиальное состояние видно polling-job'у Phase 6.3); `RequestWithdrawalData`/`Result`; contracts `HotWalletResolver`/`WithdrawalIdGenerator`/`WithdrawalEventDispatcher`. `Withdrawal::Infrastructure`: миграция `withdrawals` (UUID id, JSONB fee_breakdown, UNIQUE idempotency_key, UNIQUE (chain_id, tx_hash), indexes (chain_id,status), (chain_id,hot_address,status), wallet_id); `WithdrawalModel` + `WithdrawalMapper` + `EloquentWithdrawalRepository`; `BitcoinTxBuilder` (greedy-largest-first UTXO через `listunspent`+`createrawtransaction`, dust threshold 546 sat, fee = sat/vB × est. vsize); `EvmTxBuilder` (EIP-1559 поля + nonce + chain_id в signing extras; RLP формирует Go-сервис); `ConfigurableTxBuilderRegistry`; `ConfigHotWalletResolver`; `UuidWithdrawalIdGenerator`; `LaravelEventDispatcher`. UI: `CreateWithdrawalController` (invokable, `POST /api/v1/withdrawals`); `CreateWithdrawalRequest` (Form Request + `Idempotency-Key` header); `WithdrawalResource`. Маршрут `routes/api.php` подключен через `bootstrap/app.php::withRouting(api: ...)`. Cross-module boundary: `EstimateFeeData::fromPrimitives(string,string)` + `Fee::Application::DTO::FeeSnapshot` + `EstimateFeeResult::toSnapshot()` — Withdrawal::Application ходит в Fee без импорта Fee::Domain. 543 PHP tests (+45 vs 6.1), PHPStan L8 clean.
  - **6.3 done.** Confirm polling + stuck/replace + chain pause. Миграции: `confirmations` колонка + `signing_extras` JSONB. Domain расширен: новые transitions `markAsConfirming/Confirmed/Stuck/Replaced` (idempotent повтор не плодит события), VO `ConfirmationObservation` (pending|confirmed|dropped), exceptions `ConfirmationLookupFailedException`/`UnsupportedConfirmationLookupException`/`ChainPausedException`, contracts `WithdrawalConfirmationLookup`/`WithdrawalConfirmationLookupRegistry`/`ChainPauseRegistry`. `WithdrawalRepository` дополнен `findActiveByChain` + `findStuckCandidates`. `Withdrawal::Application`: `UpdateWithdrawalConfirmationsAction` (Broadcasted→Confirming→Confirmed по `chain.required_confirmations`), `MarkStuckWithdrawalsAction` (по `withdrawal.stuck_after_seconds`), `ReplaceStuckWithdrawalAction` (BTC RBF: тот же UTXO-set + sequence 0xfffffffd + fee×bump; EVM resend: тот же nonce + gas×bump; идемпотентность по `idempotency_key='rbf:'.{original_id}`). `Withdrawal::Infrastructure`: `BitcoinWithdrawalConfirmationLookup` (`getrawtransaction <txid> 1`, error -5 → dropped), `EvmWithdrawalConfirmationLookup` (`eth_getTransactionByHash`+`eth_blockNumber`), `CacheChainPauseRegistry` (Laravel Cache, ключ `withdrawal:chain_paused:{chain_id}` TTL 24h), `WatchWithdrawalConfirmationsJob` (per-chain queue `confirmations.{chain}` every 30s), `WatchStuckWithdrawalsJob` (`withdrawals.{chain}` every 60s), listeners `ReplaceOnWithdrawalStuck` + `PauseChainOnReorgTooDeep`. TxBuilder контракт расширен `rebuild(..., previousNonce, previousExtras)`. Withdrawal aggregate теперь хранит `signing_extras` (нужно для BIP-125 RBF: replacement обязан использовать те же UTXO). Контроллер маппит `ChainPausedException` → 503 `chain_paused`. Schedule в `routes/console.php` диспатчит per-chain jobs. 562 PHP tests (+19 vs 6.2), PHPStan L8 clean.
- [x] **Phase 7 — NodeHealth + Webhook + Idempotency** (7.1 + 7.2 + 7.3 done)
  - **7.1 done.** NodeHealth module + per-chain endpoint picker. Network расширен Domain contract `RpcEndpointPicker` (default `FirstHttpEndpointPicker` в `Network::Infrastructure\Picker`; перебивается `HealthBasedRpcEndpointPicker` из NodeHealth). NodeHealth::Domain: VOs `EndpointKey` / `EndpointStatus` (enum Unknown|Healthy|Degraded|Unhealthy + isUsable) / `EndpointObservation` (immutable, factory methods `healthy`/`degraded`/`unhealthy`), contracts `EndpointHealthRegistry` / `EndpointHealthProbe` / `EndpointHealthProbeRegistry`, event `EndpointHealthChanged` (только на transitions), exception `UnsupportedProbeFamilyException`. Application: `ProbeEndpointAction` (один endpoint → observation → registry → event если статус изменился) + `ProbeChainEndpointsAction` (iterate chain.endpoints HTTP-kind). Infrastructure: `BitcoinEndpointHealthProbe` (`getblockcount`, latency через microtime, ≥2000ms → Degraded), `EvmEndpointHealthProbe` (`eth_blockNumber`), `CacheEndpointHealthRegistry` (Laravel Cache, TTL 1h, index per chain), `HealthBasedRpcEndpointPicker` (приоритет Healthy → Degraded → Unknown; пропускает Unhealthy; throws `NoRpcEndpointException` если все мёртвые), `ProbeChainEndpointsJob` per-chain queue `health.{chain_id}`. Schedule every 30s в `routes/console.php`. Конфиг `config/node_health.php`. Рефакторинг: `EvmAdapter` / `EvmTxBuilder` / `EvmWithdrawalConfirmationLookup` / `WithdrawalServiceProvider::buildBitcoinRpc` теперь инжектят `RpcEndpointPicker` вместо ручного перебора `chain->endpoints()`. ServiceProvider регистрируется в `bootstrap/providers.php` ПОСЛЕ Withdrawal'а, чтобы его register() перебивал биндинг picker'а на HealthBased. 583 PHP tests (+21 vs 6.3), PHPStan L8 clean.
  - **7.2 done.** Webhook module через Outbox pattern + HMAC-SHA256 подпись. Domain: VOs `WebhookSubscriptionId` / `WebhookDeliveryId` / `OutboxMessageId` (UUID-validated) / `WebhookUrl` (https only by default; `allow_insecure_urls` config flag) / `WebhookSecret` (32..128 chars + `masked()`) / `WebhookEventName` (`lower.snake_case.dot.separated`) / `WebhookSignature` (HMAC-SHA256 `sha256=<hex>`, `compute()` + `verify()` через `hash_equals`) / `WebhookDeliveryStatus` enum (Pending|Delivered|Failed + `assertCanTransitionTo`) / `WebhookDispatchOutcome` (success / retryable / failed factories). Entities: `WebhookSubscription` (immutable, config-driven; method `matches(event)`), `OutboxMessage` (record/reconstitute + `markPublished`/`recordFailure`), `WebhookDelivery` (schedule/reconstitute + `markDelivered(2xx)` / `markFailed(reason)` / `reschedule(nextAttempt)` — Pending→Pending для retry, событий не эмитит). Events `WebhookDelivered` / `WebhookDeliveryFailed`. Repository contracts (subscription/outbox/delivery). Dispatcher contract. Application: `RecordOutboxMessageAction` (Infrastructure listener'ы пишут outbox при `WithdrawalConfirmed` / `TransactionConfirmed` / `ReorgDetected`), `PublishOutboxAction` (batch findUnpublished → fan-out в matching subscriptions через `findActiveForEvent` + `whereJsonContains('events', ...)` Postgres jsonb → create deliveries → markPublished в одной транзакции per message; UNIQUE (outbox_id, subscription_id) защищает от дубликатов), `DispatchWebhookDeliveryAction` (single attempt: success=2xx → Delivered + event; retryable=5xx/timeout/DNS → reschedule по `config('webhook.retry.backoff_seconds')` пока `attempts+1 < max_attempts`, иначе Failed + event; non-retryable=4xx → Failed + event). Plus `IdGenerator` + `WebhookEventDispatcher` contracts. Infrastructure: миграция `2026_06_01_000001_create_webhook_tables` (outbox_messages + webhook_subscriptions + webhook_deliveries; индексы для unpublished / due / event_name). Eloquent models + mappers + repositories. `HttpWebhookDispatcher` (Stripe-style headers `X-Signature`/`X-Timestamp`/`X-Webhook-Event`/`X-Webhook-Delivery`, body = JSON `{event, delivery_id, data}`, 5s timeout, classify 2xx/4xx/5xx). `UuidIdGenerator`, `LaravelEventDispatcher`. Jobs `PublishOutboxJob` (queue `outbox`, every minute) + `DispatchDueDeliveriesJob` (queue `webhooks`, every minute). Cross-module listeners в `Webhook::Infrastructure\Listener` подписаны на `WithdrawalConfirmed` / `TransactionConfirmed` / `ReorgDetected` (Infrastructure→чужой Domain — разрешено). Конфиг `src/config/webhook.php` (retry backoff `[30, 120, 600, 3600]`, max_attempts=5, allow_insecure_urls). Schedule добавлен в `routes/console.php`. 611 PHP tests (+28 vs 7.1), PHPStan L8 clean.
  - **7.3 done.** Глобальный IdempotencyMiddleware. Новый Module-First DDD-модуль `Idempotency`. Domain: VOs `IdempotencyKey` (8..120, charset `[A-Za-z0-9_\-:.]`), `RequestHash` (sha256 hex 64 + `equals` через `hash_equals`), `HttpMethod`, `HttpPath`, `ResponseSnapshot` (status 200..499 — 1xx без тела, 5xx transient'ы не кешируем), entity `IdempotencyRecord` (immutable, `matches`/`isExpired`), contracts `IdempotencyStore` + `Clock`, exception `IdempotencyConflictException`. Application: `LookupIdempotentResponseAction` (find + ignore expired + throw on hash mismatch) + `RecordIdempotentResponseAction` (upsert с TTL). Infrastructure: миграция `2026_06_15_000001_create_idempotency_keys_table` (key PK varchar(120), request_hash char(64), method/path, response_status/response_body, expires_at indexed), `IdempotencyKeyModel` + `EloquentIdempotencyStore` (`updateOrCreate` для upsert, `deleteExpired` по `expires_at <= now`), `SystemClock`, `CleanupExpiredIdempotencyKeysJob` (scheduled daily). `IdempotencyMiddleware` (UI/Http): pass-through на не-POST и без header'а; 400 `idempotency_key_invalid` на bad key; 409 `idempotency_conflict` на mismatch; replay сохраняет оригинальный status+body, добавляет `X-Idempotent-Replay: true`; кеширует только 2xx-4xx (5xx — transient). Зарегистрирован в `bootstrap/app.php::withMiddleware(api: ..., append: [IdempotencyMiddleware])`. ServiceProvider в `bootstrap/providers.php`. Конфиг `src/config/idempotency.php` (`ttl_seconds=86400`). Withdrawal-локальная `findByIdempotencyKey`-проверка остаётся (DB-level UNIQUE на `withdrawals.idempotency_key`). 708 PHP tests (+97 vs 7.2), PHPStan L8 clean.
- [x] **Phase 8 — Education UI + playgrounds** (8.1 + 8.2 + 8.3 + 8.4 done)
  - **8.1 done.** Inertia + Vue 3 foundation + 2 уроков + 3 страницы. composer: `inertiajs/inertia-laravel`, `league/commonmark`, `spatie/yaml-front-matter`. npm (manual install потом): `vue@^3.5`, `@inertiajs/vue3@^2`, `@vitejs/plugin-vue@^5`. Education::Domain: VO `LessonSlug` (kebab-case 3..80), `LessonModule` (`NN-name` + `order()` + `displayName()`), `LessonOrder` (1..99). Entity `Lesson` (immutable: slug + module + order + title + summary + bodyHtml + ?playgroundId). Repository `LessonRepository`. Exception `LessonNotFoundException`. Application: DTOs `LessonSummary` + `LessonView`, actions `ListLessonsAction` + `ShowLessonAction`. Infrastructure: `MarkdownLessonRepository` (читает `content/lessons/{NN-module}/{NN-slug}.md`, парсит YAML frontmatter + commonmark, in-memory кеш), `EducationServiceProvider`. UI: invokable controllers `ShowHomeController` / `ShowLessonIndexController` / `ShowLessonController` (NotFoundHttpException на bad slug + LessonNotFoundException). Routes `/` + `/lessons` + `/lessons/{slug}` в `routes/web.php`. Frontend: `resources/views/app.blade.php`, `resources/js/app.js` (Inertia + Vue setup), `Layouts/MainLayout.vue`, pages `Home.vue` / `Lessons/Index.vue` / `Lessons/Show.vue` (left nav + content + playground placeholder right column). Tailwind 4 уже подключён. `vite.config.js` расширен `@vitejs/plugin-vue`. Inertia config опубликован: `pages.paths = resource_path('js/Pages')` (capitalized). Контент: `src/content/lessons/01-foundations/{01-what-is-blockchain.md, 02-keypairs-and-signatures.md}`. Конфиг `config/education.php`. 735 PHP tests (+27 vs 7.3), PHPStan L8 clean.
  - **8.2 done.** 3 crypto playgrounds (Keypair / RawTxEditor / SignDecode) — wired end-to-end. Go signing-svc: новый `internal/playground` package + 3 endpoint'а под /v1/playground/* (за Bearer auth). `keypair.go` — bip39 mnemonic + bip32 derivation по m/44'/0'/0'/0/0 + btcec secp256k1 + btcutil P2WPKH (bech32, mainnet) + go-ethereum keccak/Address + manual Base58Check для Tron. `sign.go` — sha256d(message) + ECDSA через `ecdsa.Sign` + `ecdsa.SignCompact` для raw r/s. `decode.go` — `btcd/wire.MsgTx.Deserialize` для BTC, `go-ethereum/core/types.Transaction.UnmarshalBinary` для EVM (legacy + access list + EIP-1559). `internal/server/playground_handlers.go` + route registration в `server.go`. Laravel: `Education::Application::Contract::PlaygroundClient` (порт), `Education::Infrastructure::Http::HttpPlaygroundClient` (Bearer-token + JSON, через `network.signing.*` config). 3 invokable controllers `Education::UI::Http::Controller::Playground\\*` (валидация через Form Request rules — regex на hex/private key/chain). Routes `/api/playground/{keypair,sign,decode}` в `routes/api.php` (без префикса `v1`, чтобы не путать с production API). Vue: `Playgrounds/Keypair.vue` / `SignDecode.vue` / `RawTxEditor.vue` — fetch + reactive state + копирование. `Lessons/Show.vue` обновлён: dynamic `<component :is>` рендерит зарегистрированный playground по `lesson.playground_id` через `shallowRef` registry. Контент: добавлены `01-foundations/03-raw-transactions.md` (playground: raw-tx-editor) + `04-signing-in-practice.md` (playground: sign-decode). Tests: Go (`keypair_test.go` — deterministic snapshot, random 12-word check, invalid mnemonic; `sign_test.go` — DER prefix + raw r/s shape), Laravel (`PlaygroundApiTest.php` — Http::fake() proxy assertions, validation failures, 502 mapping). 742 PHP tests (+7 vs 8.1), PHPStan L8 clean, Go tests pass.
  - **8.3 done.** 4 sim playgrounds (MempoolTracker / ReorgSimulator / GasChart / NonceConflict). Education::Application::Contract::`RegtestRpcClient` + Education::Infrastructure::Http::`HttpRegtestRpcClient` — собственный JSON-RPC client к bitcoind (избегает зависимости от BlockIngestion). 4 invokable controllers поверх regtest (`GetMempool`, `GetRegtestState`, `MineBlocks`, `InvalidateTip`) + 2 synthetic (`GasChart` — экспоненциальный decay с seed-based noise; `NonceConflict` — простая EIP-1559 replacement rule simulator). Routes под `/api/playground/{regtest/{mempool,state,mine,invalidate-tip}, gas/chart, nonce/simulate}`. Vue: `MempoolTracker.vue` (2s polling через `setInterval`), `ReorgSimulator.vue` (mine N + invalidate-tip + recent блоки), `GasChart.vue` (inline SVG bar chart), `NonceConflict.vue` (form + симуляция). Все 4 зарегистрированы в `Lessons/Show.vue` playgroundRegistry. Контент: `02-bitcoin/01-mempool.md` (playground: mempool-tracker), `02-bitcoin/02-reorg.md` (playground: reorg-simulator), `03-ethereum/01-gas-mechanics.md` (playground: gas-chart), `03-ethereum/02-nonce-management.md` (playground: nonce-conflict). Конфиг `education.regtest.{url,user,password}`. Tests: `RegtestPlaygroundTest.php` (Http::fake() sequence через bitcoind RPC + проверка 502 mapping) + `SyntheticPlaygroundTest.php` (seed determinism + nonce winner логика). 753 PHP tests (+11 vs 8.2), PHPStan L8 clean.
  - **8.4 done.** Admin dashboard + 2 system-design урока. `Education::Application::DTO\Dashboard\*` — read-DTO (NodeHealthEndpointRow + 5 секций + `DashboardOverview` aggregate). `Education::Application::Contract\Dashboard\*` — 5 порт-провайдеров (NodeHealth / Mempool / Ledger / WithdrawalQueue / Outbox). `LoadDashboardOverviewAction` — оркестратор всех 5 в один payload. `Education::Infrastructure\Dashboard\Provider\*` — 5 реализаций: `RegistryNodeHealthOverviewProvider` (ChainRepository + EndpointHealthRegistry; пропускает WebSocket-endpoint'ы, Unknown для cold-start), `RegtestMempoolOverviewProvider` (только Bitcoin family через RegtestRpcClient, для EVM/Tron возвращает `tx_count=null` + explanatory error), `EloquentLedgerOverviewProvider` / `EloquentWithdrawalQueueOverviewProvider` / `EloquentOutboxOverviewProvider` — поверх собственных lookup-моделей в `Education::Infrastructure\Persistence\Eloquent\*LookupModel` (не дублируют чужие Infrastructure, держат узкий read-only касет). UI: `Admin\ShowAdminDashboardController` (GET /admin Inertia), `Admin\GetAdminDashboardController` (GET /api/admin/dashboard JSON), `Admin/Dashboard.vue` (5 секций, 5s polling через `setInterval` + fetch, age-badge). Nav-ссылка `Admin` в MainLayout. Контент: `05-system-design/01-multisig-2-of-3.md` (Bitcoin Script vs Ethereum Safe + что меняется в Withdrawal'е) + `05-system-design/02-why-hsm.md` (envelope encryption + куда mapит'ся signing-svc). Tests: 4 integration (`Dashboard/Eloquent*ProviderTest.php` + `RegistryNodeHealthOverviewProviderTest.php`) + 1 feature (`AdminDashboardTest.php` с Http::fake bitcoind). 767 PHP tests (+14 vs 8.3), PHPStan L8 clean.
- [ ] Phase 9+ — Production hardening (mainnet, L2 add-ons, HSM, multi-sig) — на паузе

**Resume protocol for new sessions:**
1. Read this section + the relevant `§6 Phase N` block below.
2. Read `docs/architecture/overview.md` and any ADRs in `docs/architecture/decisions/`.
3. Check memory file `project_blockchain_lab_scope.md` for latest non-code context.
4. `git log --oneline -10` to see what was last committed.
5. Continue from "Next action" above.

---

## 0. Зафиксированные решения

| Параметр | Значение |
|---|---|
| Архитектура | Modular monolith (Laravel) + изолированный signing service (Go) |
| Раскладка | Module-First DDD: `src/app/Modules/{Context}/{Domain,Application,Infrastructure,UI}` |
| Стек | PHP 8.4 · Laravel 13 · Pest 4 · PHPStan L8 · Pint · Horizon · Inertia + Vue 3 (Phase 8) · Go (signing-svc) |
| БД | **PostgreSQL 16** (advisory locks, `jsonb`, `timestamptz`, partial indexes) |
| Кэш / очереди / locks | Redis 7 + Horizon |
| APP_ROOT | `src` (весь PHP-код и тесты под `src/`) |
| MVP сети | Bitcoin regtest + testnet · Ethereum Sepolia · Tron Shasta · Polygon Amoy |
| Workflow | Docker-only, hooks `secret-guard.sh` + `php-postwrite.sh`, команды `/phpstan` `/test` |
| Шифрование ключей | Envelope encryption (master key в KMS / Vault / SoftHSM, DEK per HD seed) |
| Подпись | Только в Go signing-svc (приватные ключи никогда не покидают сервис) |
| PHP-альтернативы wallet-core | Не используем — фрагментарны, плохо покрывают новые сети. PHP — только валидация адресов и orchestration |

### Темы из требований, покрываемые архитектурой

| Концепт | Где живёт |
|---|---|
| Gas mechanics | `Modules/Fee` + урок `03-ethereum/gas-mechanics.md` + playground |
| Mempool | `Modules/Transaction` + урок `02-bitcoin/mempool.md` |
| Confirmations | `Modules/Confirmation` + finality policy per chain |
| Reorg / rollback | `Modules/ReorgDetection` + `Modules/Ledger` (reversal entries) |
| Raw transactions | Go signing-svc + урок + playground (hex editor) |
| Signing | Go signing-svc (wallet-core) |
| Private keys | KMS / Vault → DEK envelope encryption, refs only в Laravel |
| Nonce management | `Modules/Withdrawal` → `NonceAllocator` (PG advisory lock) |
| Stuck transactions | `Modules/Withdrawal` → `WatchStuckWithdrawalsJob` + RBF / resend |
| Node monitoring | `Modules/NodeHealth` + circuit breaker per RPC |
| Hard fork / chain upgrade | `Modules/Network` → `ChainSpec` версионирование |
| Unified interface | `Modules/Network::ChainAdapter` интерфейс |

---

## 1. Микросервисная карта

```
┌─────────────────────┐       ┌─────────────────────┐
│  Laravel: API +     │ HTTPS │  Go: signing-svc    │  ← приватные ключи только здесь
│  Education + UI     ├──────▶│  (wallet-core)      │     mTLS, audit log, KMS
│  (PHP 8.4, L13)     │       │  + KMS adapter      │
└──────────┬──────────┘       └─────────────────────┘
           │
   ┌───────┴───────────────────────────────────────┐
   ▼           ▼            ▼            ▼          ▼
┌─────┐   ┌─────────┐  ┌──────────┐ ┌─────┐ ┌──────────┐
│scan-│   │confirm- │  │withdrawal│ │fee  │ │node-     │
│ner  │   │tracker  │  │worker    │ │poll │ │watchdog  │
└──┬──┘   └────┬────┘  └────┬─────┘ └──┬──┘ └────┬─────┘
   │           │            │          │         │
   ▼           ▼            ▼          ▼         ▼
┌──────────────────────────────────────────────────────┐
│  RPC pool: own nodes + Infura/Ankr/QuickNode         │
└──────────────────────────────────────────────────────┘
        │                       │
        ▼                       ▼
   ┌──────────┐           ┌──────────┐
   │PostgreSQL│           │  Redis   │
   └──────────┘           └──────────┘
```

**Воркеры** — это Laravel-команды + Horizon supervisors, **не отдельные сервисы**. Per-chain queues для bulkhead-изоляции.

---

## 2. Bounded contexts (13)

```
src/app/Modules/
├── Network/            # реестр сетей, RPC, ChainSpec, ChainAdapter
├── Address/            # HD-кошельки, derivation, валидация
├── BlockIngestion/     # сканер блоков, head tracking
├── ReorgDetection/     # выявление reorg, компенсация
├── Transaction/        # парсинг tx, классификация
├── Confirmation/       # счётчик подтверждений, finality
├── Ledger/             # двойная запись балансов
├── Wallet/             # пользовательские кошельки + баланс
├── Withdrawal/         # исходящие транзакции, nonce, RBF
├── Fee/                # оценка комиссий per chain
├── NodeHealth/         # мониторинг RPC, circuit breaker
├── Webhook/            # outbound события (outbox)
├── Idempotency/        # глобальный HTTP-level replay + 409 на conflict
└── Education/          # уроки, sandbox-задачи, теория
```

### Ключевой полиморфизм: `Network::ChainAdapter`

```php
interface ChainAdapter
{
    public function chainId(): ChainId;
    public function family(): ChainFamily;             // BITCOIN | EVM | TRON
    public function finality(): FinalityPolicy;
    public function fetchBlock(BlockHeight $h): RawBlock;
    public function fetchTransaction(TxHash $h): ?RawTransaction;
    public function broadcast(SignedRawTx $tx): TxHash;
    public function feeEstimator(): FeeEstimator;
    public function addressValidator(): AddressValidator;
    public function txDecoder(): TxDecoder;
}
```

Реализации: `BitcoinAdapter`, `EvmAdapter` (общий на ETH/Polygon/Arbitrum/Base/Optimism — отличается `ChainSpec`), `TronAdapter`. **Новая L2-сеть = новый `ChainSpec`, никакого нового кода.**

---

## 3. Schema (PostgreSQL highlights)

```sql
chains(
  id PK, name, family, native_currency,
  required_confirmations INT, max_reorg_depth INT,
  finality_policy JSONB, enabled BOOLEAN
);

chain_rpc_endpoints(
  id, chain_id FK, url, kind ENUM(http,ws),
  priority, weight, health_status, last_check_at TIMESTAMPTZ
);

blocks(
  chain_id, height, hash CHAR(66), parent_hash CHAR(66),
  timestamp TIMESTAMPTZ, scanned_at TIMESTAMPTZ,
  PRIMARY KEY (chain_id, height),
  UNIQUE (chain_id, hash)
);

scan_cursors(chain_id PK, last_scanned_height, last_seen_head, updated_at);

hd_seeds(id, ref_in_kms, created_at);            -- ТОЛЬКО ref, никаких ключей

addresses(
  id, chain_family, address VARCHAR(96) UNIQUE,
  derivation_path, hd_seed_id, wallet_id, created_at
);

incoming_transactions(
  id, chain_id, tx_hash, block_height NULL, block_hash NULL,
  from_address, to_address, amount NUMERIC(40,0),
  currency, status,        -- detected|confirming|confirmed|orphaned|finalized
  confirmations INT DEFAULT 0,
  UNIQUE (chain_id, tx_hash),
  INDEX (chain_id, to_address, status),
  INDEX (chain_id, block_height) WHERE status IN ('detected','confirming')
);

withdrawals(
  id, wallet_id, chain_id, to_address, amount NUMERIC(40,0),
  currency, status, tx_hash NULL, replacement_of NULL,
  version INT, idempotency_key UNIQUE,
  requested_at, broadcast_at, confirmed_at
);

nonce_assignments(
  chain_id, hot_address, nonce,
  withdrawal_id, allocated_at, used_at,
  PRIMARY KEY (chain_id, hot_address, nonce)
);

ledger_entries(                                 -- двойная запись для reorg-safety
  id, wallet_id, direction ENUM(debit,credit),
  amount NUMERIC(40,0), currency,
  operation_id, operation_type,                 -- deposit|withdrawal|fee|reorg_reversal
  status ENUM(pending,confirmed,reversed),
  related_tx_hash, block_height NULL, version,
  created_at TIMESTAMPTZ
);

outbox_messages(
  id UUID PK, aggregate_id, type, payload JSONB,
  created_at TIMESTAMPTZ, published_at TIMESTAMPTZ NULL,
  attempts INT DEFAULT 0, last_error TEXT
);

idempotency_keys(
  key PK, user_id, request_hash, response JSONB,
  status_code, created_at, expires_at
);
```

Все суммы — `NUMERIC(40,0)` в minor units (wei / satoshi / sun). Никогда не float.

---

## 4. State machines

### Incoming transaction
```
detected → confirming(N) → confirmed → finalized
              ↘
               orphaned (reorg) → re-detected | dropped
```

### Withdrawal
```
requested → fee_estimated → built → signed → broadcasted
                                                ↓
                              ┌──── confirming ──┤
                              │                  ↓
                              │             confirmed
                              ↓
                          stuck (TTL) → replaced (RBF / nonce-resend) → broadcasted
                              ↓
                          failed (insufficient_funds | nonce_gap | …)
```

---

## 5. Education layer

```
src/content/lessons/
├── 01-foundations/        # crypto primitives (secp256k1, hash functions)
├── 02-bitcoin/            # UTXO, mempool, confirmations, reorg, RBF/CPFP
├── 03-ethereum/           # account model, gas, EIP-1559, nonce, raw tx, L2 rollups
├── 04-tron/               # bandwidth/energy, SR-консенсус
└── 05-system-design/      # building a scanner, reorg compensation, hot wallet design
```

Каждый урок — три части:
1. Аналогия для новичка
2. Точное определение / протокол
3. **Ссылка на конкретные классы платформы**

Playground'ы (Inertia + Vue) дёргают реальные endpoint'ы платформы:
- Keypair generator → `POST /addresses/derive` (Go)
- Raw TX editor → собрать tx hex руками → подписать → расшифровать
- Mempool tracker → отправить tx → следить `pending → mined`
- **Reorg simulator** → admin force-invalidates regtest block → студент видит `confirmed → orphaned` в БД
- Gas vs confirmation time → меняешь priority fee → смотришь время mined
- Nonce conflict → две tx с одним nonce → одна вытесняет другую

---

## 6. Поэтапный план

Каждая фаза = работающий инкремент. Тесты пишутся **в той же фазе**, что и код.

---

### Phase 0 — Bootstrap (~1 неделя)

**Цель:** работающий dev-environment, чистая база, quality gates.

- [ ] `docker-compose.yml`: `app` (php-fpm 8.4) · `nginx` · `postgres-16` · `redis-7` · `horizon` · `bitcoin-regtest` · `signing-svc-stub`
- [ ] `Dockerfile` для Laravel (extensions: `bcmath`, `gmp`, `pdo_pgsql`, `redis`, `opcache`)
- [ ] `composer create-project laravel/laravel src` под `APP_ROOT=src`
- [ ] `src/composer.json` зависимости:
  - prod: `laravel/framework:^13`, `laravel/horizon`, `laravel/sanctum`, `spatie/laravel-data`, `spatie/laravel-permission`, `spatie/laravel-query-builder`
  - dev: `pestphp/pest`, `pestphp/pest-plugin-laravel`, `pestphp/pest-plugin-arch`, `phpstan/phpstan`, `larastan/larastan`, `laravel/pint`
- [ ] PSR-4: `App\Modules\` → `app/Modules/`
- [ ] `src/phpstan.neon` (level 8)
- [ ] `src/pint.json` (PSR-12 strict)
- [ ] `src/tests/Pest.php` базовая конфигурация
- [ ] `src/tests/Architecture/LayersTest.php` — arch-правила (Domain без Illuminate)
- [ ] `src/tests/Architecture/ModuleBoundariesTest.php` — модули не зависят друг от друга напрямую
- [ ] `.claude/settings.local.json` — `CLAUDE_APP_ROOT=src`, `CLAUDE_PHP_CONTAINER=blockchain-lab-app`
- [ ] `Makefile` — `up`, `down`, `test`, `stan`, `pint`, `shell`
- [ ] `.editorconfig`, `.gitattributes`, `.env.example`
- [ ] `README.md` quickstart
- [ ] `docs/architecture/overview.md` — карта 13 контекстов
- [ ] ADRs: `0001-modular-monolith.md`, `0002-go-signing-service.md`, `0003-postgresql-not-mysql.md`

**Done criteria:** `make up && make test && make stan` зелёное.

---

### Phase 1 — Network + ChainAdapter (~1 неделя)

**Цель:** один интерфейс для всех сетей, реестр сетей в БД.

- [ ] Модуль `Network/Domain`: `ChainAdapter`, `FinalityPolicy`, `AddressValidator`, `FeeEstimator`, `TxDecoder` интерфейсы
- [ ] VOs: `ChainId`, `ChainFamily` (enum), `BlockHeight`, `TxHash`, `Address`, `RawBlock`, `RawTransaction`, `SignedRawTx`
- [ ] Миграции: `chains`, `chain_rpc_endpoints`
- [ ] `Network/Infrastructure`: `EloquentChainRegistry`, `ChainSpecLoader`
- [ ] `Network/Application/UseCase/RegisterChain` (для seed-команды)
- [ ] CLI: `php artisan chain:register {name}` для регистрации BTC regtest, ETH Sepolia, Tron Shasta, Polygon Amoy
- [ ] `NoOpChainAdapter` для unit-тестов
- [ ] Arch-тесты: Network не зависит ни от какого другого модуля

**Done criteria:** registry возвращает 4 сети, каждая адресуется через `ChainAdapter`.

---

### Phase 2 — Go signing service (~1.5 недели)

**Цель:** изолированный signing service, mTLS, KMS-backed.

- [ ] `signing-svc/` Go module
- [ ] cgo bindings к `trustwallet/wallet-core` (или wrapper если нужен дополнительный слой)
- [ ] Endpoints (gRPC опционально):
  - `POST /addresses/derive { coin, hd_seed_ref, path }`
  - `POST /addresses/validate { coin, address }`
  - `POST /tx/sign { coin, raw_tx, key_ref }`
  - `POST /tx/decode { coin, raw_tx }`
- [ ] KMS-адаптер:
  - dev: SoftHSM
  - prod: AWS KMS (`aws-sdk-go-v2`) / HashiCorp Vault
  - Envelope encryption: master в KMS, DEK on disk encrypted
- [ ] mTLS между Laravel и signing-svc
- [ ] Audit log (структурированный) каждой операции
- [ ] Laravel `Infrastructure/Signer/HttpSigningClient` с timeout/retry/circuit breaker
- [ ] Integration test: Laravel → signing-svc → подпись tx на Sepolia → broadcast → проверка через external explorer

**Done criteria:** мы можем сгенерировать ETH-адрес, подписать transfer и broadcast'нуть на Sepolia.

---

### Phase 3 — Address + HD wallets (~1 неделя)

**Цель:** генерация и валидация адресов для всех 4 сетей.

- [ ] Модуль `Address/Domain`: `HdSeed`, `Address`, `DerivationPath` VO
- [ ] Миграции: `hd_seeds`, `addresses` (с partial unique index на `address`)
- [ ] `Address/Application/UseCase/GenerateAddress`: → signing-svc `/addresses/derive`
- [ ] `Address/Application/UseCase/ValidateAddress`: → signing-svc `/addresses/validate` + Redis cache
- [ ] BIP-44 derivation paths:
  - BTC: `m/44'/0'/0'/0/i`
  - ETH/Polygon: `m/44'/60'/0'/0/i`
  - Tron: `m/44'/195'/0'/0/i`
- [ ] Test vectors против эталонных значений (BIP-44 test vectors, EIP-55 checksums, Bitcoin Improvement Proposals examples)
- [ ] Feature test: создать seed → деривация → валидация → roundtrip

**Done criteria:** для каждой сети генерируется валидный адрес, проходит external validation.

---

### Phase 4 — BlockIngestion + Confirmation (~2 недели) ✅

> Locked scope decisions are in `memory/phase_4_design.md` (BTC regtest only, Redis SET not RedisBloom, polling not WebSocket, real BitcoinAdapter while EVM/Tron stay NoOp, cross-module integration via `AddressDirectory` contract).

**Цель:** real-time сканирование Bitcoin regtest + корректный счётчик подтверждений.

- [x] Модуль `BlockIngestion`:
  - Aggregates `Block`, `ScanCursor`, `IncomingTransaction`
  - VOs `Amount`, `Currency`, `IncomingTxStatus` (detected → confirming → confirmed state machine)
  - Миграция `2026_05_23_000001_create_block_ingestion_tables.php` — `blocks`, `scan_cursors`, `incoming_transactions`
  - `BlockSource` + `BlockSourceFactory` ports; `BitcoinCoreBlockSource` polling impl via JSON-RPC 1.0 (`getblockcount`, `getblockhash`, `getblock $hash 2`)
  - Actions: `InitChainCursor` (idempotent baseline at current head), `ScanNextBlock` (per-block transaction, events via afterCommit)
  - `ScanChainJob` on `scan.{chain}` queue with `Cache::lock` for single-writer protection
  - Console commands: `scan:init {chain}`, `scan:run {chain} --max=N`
- [x] Watched-address index в Redis SET (`watched:{family}`) — `RedisAddressDirectory` (Address::Infrastructure) реализует `AddressDirectory` (BlockIngestion::Domain). `InMemoryAddressDirectory` для тестов. Listener `RegisterAddressInDirectory` подписан на `AddressGenerated` — Address модуль publish'ит, BlockIngestion консьюмит через контракт.
- [x] Сохранение `incoming_transactions` со статусом `detected` (UNIQUE (chain_id, tx_hash, to_address) — одна tx может платить нескольким нашим адресам).
- [x] Эмит `BlockIngested` + `TransactionDetected` events.
- [x] Модуль `Confirmation`:
  - `ConfirmationCalculator` pure service: `confirmations = lastScannedHeight - txBlockHeight + 1`
  - `UpdateConfirmationsAction` обновляет статусы (Detected → Confirming → Confirmed) и эмитит `TransactionConfirming` / `TransactionConfirmed`
  - Свой Eloquent-`IncomingTransactionRowModel` поверх той же таблицы — никакого импорта BlockIngestion::Domain
  - `ChainScannerHead` port + `EloquentChainScannerHead` impl поверх `scan_cursors`
  - `UpdateConfirmationsJob` on `confirmations.{chain}` queue
- [x] Реальный `BitcoinAdapter` (Network::Infrastructure) — `currentHead()` / `isHealthy()` через `BlockSource`, address validation через signing-svc. EVM/Tron остаются на `NoOpChainAdapter`.
- [x] Тесты: arch (auto-enumerated) + unit (`IncomingTxStatus`, `Amount`, `Block`, `ScanCursor`, `IncomingTransaction`, `ConfirmationCalculator`) + integration (round-trip репозиториев) + feature (полный flow scan + confirmation через `FakeBlockSourceFactory`).

**Done criteria:** 421 теста зелёные (+40 vs Phase 3), PHPStan L8 clean. Полный flow detected → confirming → confirmed проходит в feature-тесте без поднятия живого bitcoind.

**Перенесено в Phase 5+:** `finalized` state (зависит от reorg-safety), WebSocket subscriber / ZMQ (Phase 7 NodeHealth), Bloom filter оптимизация (Phase 9), backfill admin command (Phase 7).

---

### Phase 5 — ReorgDetection + Ledger (~2 недели) ✅

> Locked scope decisions: `memory/phase_5_design.md`. Wallet read-model отложен — балансы пока вычисляются из `ledger_entries` напрямую.

**Цель:** корректная компенсация при reorg, audit-trail балансов.

- [x] Расширены state machines: `IncomingTxStatus` + `ConfirmationOutcome` получили `Finalized` (depth > max_reorg_depth) и `Orphaned` (reorg).
- [x] `ConfirmationCalculator` принимает `maxReorgDepth` и эмитит `TransactionFinalized`.
- [x] `BlockRepository` расширен `findByHash(ChainId, BlockHash)` и `latestForChain(ChainId)`.
- [x] Модуль `ReorgDetection`:
  - Domain: `ChainHistory` (read-port over `blocks`), `ReorgWriter` (write-port для orphan + delete + cursor rollback), `ChainComparator` сервис, события `ReorgDetected` + `ReorgTooDeep` (заглушка под Phase 6 alert wiring).
  - Application: `EvaluateBlockReorgAction` — единственная точка входа. Single-tick parent_hash check, итеративный walk-back через rollback курсора.
  - Infrastructure: `EloquentChainHistory`, `EloquentReorgWriter` (через собственные Eloquent-модели поверх общих таблиц), `EvaluateOnBlockIngested` listener подписан на `BlockIngestion::Domain::Event::BlockIngested`.
- [x] Модуль `Ledger`:
  - Domain: `LedgerEntry` aggregate (двойная запись, NEVER delete — только reversal), VOs (`Direction`, `OperationType`, `EntryStatus`, `Money`, `OperationRef`, локальный `WalletId`), `WalletOwnership` + `ConfirmedTransactionView` Domain ports, `LedgerEntryRepository`.
  - Application: `RecordLedgerCreditAction` (идемпотентен по `operation_type` + `operation_ref`), `ReverseLedgerForReorgAction` (создаёт встречную проводку, метит оригинал `Reversed`).
  - Infrastructure: миграция `ledger_entries` (UNIQUE (operation_type, operation_ref) — основа идемпотентности), `RecordCreditOnConfirmed` listener на `TransactionConfirmed`, `ReverseLedgerOnReorg` на `ReorgDetected`. Адаптеры `EloquentWalletOwnership` (чит. `addresses`) и `EloquentConfirmedTransactionView` (чит. `incoming_transactions`) — без импортов чужих Domain.
- [x] `ScanNextBlockAction` исправлен: финальный cursor save сделан conditional, чтобы ReorgDetection rollback переживал тик скана.
- [x] **Reorg test scenario** (bitcoind regtest): `tests/Feature/Live/ReorgSimulatorTest.php` — полный flow (mine 101 → deposit → mine 3 → confirm → ledger credit → invalidateblock → mine 7 alt → итеративный orphan → ledger reversal). Suite пропускается по умолчанию; запуск: `BITCOIN_LIVE_TESTS=1` + поднятый `bitcoin-regtest`.
- [x] Arch + unit (`IncomingTxStatus`, `ChainComparator`, `Money`, `LedgerEntry`, расширенный `ConfirmationCalculator`) + integration (`EloquentBlockRepository`, `EloquentChainHistory`, `EloquentLedgerEntryRepository`) + feature (`ReorgDetectionTest`, `LedgerFlowTest`).

**Done criteria:** 460 тестов зелёные (+39 vs Phase 4), PHPStan L8 clean. Reorg-симулятор корректно компенсирует баланс, ledger содержит полную audit-trail (credit Reversed + reversal Confirmed).

**Перенесено в Phase 6+:** `Wallet` read-model и projection в `wallet_balance_view` (Phase 6); `ReorgTooDeep` handler — остановка withdrawal worker + оператор-алерт (Phase 6); накопление общей глубины reorg на серию событий (Phase 7 NodeHealth); admin reorg-simulator UI с кнопкой `invalidateblock` (Phase 8).

---

### Phase 6 — Withdrawal + Fee + Nonce (~2 недели)

> Locked scope decisions: `memory/phase_6_design.md` (BTC+EVM happy path; Tron NoOp; config-based hot wallet; `bitcoind listunspent` UTXO source). Split into 6.1 (Fee + NonceAllocator), 6.2 (Withdrawal core + HTTP + broadcast/signRawTx), 6.3 (Stuck/RBF + ReorgTooDeep + live BTC test).

**Цель:** надёжные исходящие транзакции под нагрузкой.

- [ ] Модуль `Fee`:
  - `EstimateFeeAction` per chain
  - BTC: `estimatesmartfee`
  - EVM: EIP-1559 (`maxFeePerGas`, `maxPriorityFeePerGas`)
  - Tron: bandwidth/energy
- [ ] Модуль `Withdrawal`:
  - Aggregate `Withdrawal` со state machine выше
  - `NonceAllocator` (PG advisory lock + unique constraint `(chain_id, hot_address, nonce)`)
  - `RequestWithdrawalAction` → fee estimate → build tx → signing-svc → broadcast
  - `BroadcastWithdrawalJob` per chain queue
  - `WatchStuckWithdrawalsJob` — каждую минуту: pending > 15 min → trigger replacement
  - `ReplaceStuckWithdrawalAction`: BTC RBF, EVM resend с тем же nonce + higher gas
  - Idempotency middleware на `POST /withdrawals`
- [ ] Тесты на testnet:
  - happy path
  - nonce gap (создать gap → проверить блокировку)
  - stuck transaction → RBF
  - insufficient hot balance
  - reorg отменяет confirmed withdrawal

**Done criteria:** withdraw на testnet проходит за < 30 сек, stuck-случаи корректно RBF'ятся.

---

### Phase 7 — NodeHealth + Webhook + Idempotency (~1 неделя)

**Цель:** устойчивость к сбоям провайдеров, надёжная доставка событий.

- [ ] Модуль `NodeHealth`:
  - Probe каждые 30 сек (`eth_blockNumber` / `getblockchaininfo`)
  - Сравнение `head_lag` между провайдерами
  - Circuit breaker per endpoint (state в Redis)
  - Failover: pool, weighted, auto-blacklist при N failures
  - **Quorum check**: тот же блок fetched с 2+ provider → сравнить hash (anti-poisoning)
- [ ] Модуль `Webhook` через **Outbox pattern**:
  - `outbox_messages` миграция
  - `PublishOutboxJob` каждую минуту (`lockForUpdate` + 100 за раз)
  - HMAC sig + `X-Timestamp` ≤ 5 min
  - Retry с exponential backoff
- [ ] `IdempotencyMiddleware` глобально на критичные POST
- [ ] Pulse / Telescope для observability в dev

**Done criteria:** убиваем primary RPC — система falls over на secondary без видимого даунтайма.

---

### Phase 8 — Education UI + Playgrounds (~2 недели)

**Цель:** учебный продукт.

- [ ] Inertia + Vue 3 + Vite setup
- [ ] Уроки в markdown (`src/content/lessons/`) — парсятся при сборке
- [ ] Layout: левый sidebar навигация по урокам, основной контент, правый sidebar — playground
- [ ] Playground'ы:
  - **Keypair generator** (Foundations)
  - **Raw TX editor** (Ethereum / Bitcoin)
  - **Sign & decode** (visualizer)
  - **Mempool tracker** (live WebSocket из platform)
  - **Reorg simulator** (admin кнопка `bitcoind invalidateblock`)
  - **Gas vs confirmation time** chart
  - **Nonce conflict** demo
- [ ] Admin dashboard:
  - Node health per chain
  - Mempool state
  - Ledger overview
  - Withdrawal queue
  - Outbox lag
- [ ] Документация архитектуры (`docs/`) с ссылками на конкретные классы

**Done criteria:** новичок проходит уроки → понимает gas / nonce / reorg → может объяснить свой первый mainnet withdrawal.

---

### Phase 9+ — Production hardening

- Mainnet rollout (после полной testnet-стабильности)
- L2 add-ons: Arbitrum / Base / Optimism (только новые `ChainSpec`)
- HSM вместо SoftHSM
- Pen-test, SAST / DAST в CI
- Octane для горячих endpoints (validate-address, fee-estimate)
- Multi-region replicas для PG (read replicas + failover)
- Multi-sig hot wallet (2-of-3)

---

## 7. Безопасность

| Угроза | Контрмера |
|---|---|
| Утечка приватных ключей | Изоляция signing-svc, KMS, mTLS, audit log, no logs |
| SQL injection | Eloquent + bindings; никаких `DB::raw` с интерполяцией |
| Mass assignment | DTO → `$fillable` явный |
| Replay (withdrawal) | `Idempotency-Key` обязателен |
| Cross-tenant утечки | Global scope `tenant_id` per query |
| RPC poisoning | Quorum: тот же block с 2+ providers |
| Brute force на login | `throttle:5,1` + 2FA на withdrawal |
| Webhook spoofing | HMAC sig + timestamp ≤ 5 min |
| PII в логах | `LogProcessor` redact |

---

## 8. High-load patterns

- Bloom filter на watched addresses (отсечка 99% не-наших)
- Sharded scanners по `address_prefix` (если > 10M адресов)
- Batch RPC (`eth_getBlockByNumber` пачками)
- Per-chain queues (bulkhead)
- Circuit breaker per RPC endpoint
- Read replicas для analytics
- Materialized read-models для dashboard (CQRS-lite)

---

## 9. Риски и trade-offs

| Риск | Mitigation |
|---|---|
| cgo overhead в Go signing | batch endpoint для multiple signatures |
| RPC rate limits на free tier | own nodes для regtest и Geth Sepolia, free providers — secondary |
| HD seed как single point of compromise | multi-sig 2-of-3 на mainnet, single seed в KMS на учебе |
| Reorg test на mainnet невозможен | regtest + симулятор + docs ограничений |
| L2 fee model отличается | `l1_fee` компонент в `EvmAdapter::feeEstimator` параметризуется per `ChainSpec` |
| Объём работы — 6+ месяцев соло | scope-down MVP опционально: BTC + ETH first, Tron / Polygon — phase 9+ |

---

## 10. Когда план обновлять

Этот документ — живой. Обновляется:
- В конце каждой фазы (отметить выполненное)
- При значимых архитектурных решениях (новый ADR + обновить ссылку здесь)
- При смене scope (фиксировать дату и причину)

Архитектурные решения — в `docs/architecture/decisions/NNNN-title.md` (ADR-формат). Каждый ADR содержит: Context · Decision · Consequences.
