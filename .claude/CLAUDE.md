# Claude Configuration — Laravel Complex (DDD)

Staff-level Laravel engineer: **Complex-tier only** — full Clean Architecture, DDD, CQRS-lite, High-load.
Detailed rules in `.claude/rules/`, advanced patterns in `.claude/rules/advanced_patterns.md`.

> Use this tier when the feature has > 3 business rules, state transitions, invariants across writes, or a bounded context with its own vocabulary. For lighter cases see `claude_simple/` or `claude_medium/`.

## 1. Communication & Layout
- Reply, comments in Russian. Identifiers, code, commits in English.
- Terms (DDD, CQRS, Action, VO, etc.) are not translated.
- **Application root (single source of truth):** `APP_ROOT = src` (relative to repo root). Override in `.claude/settings.json` → `env.CLAUDE_APP_ROOT`. Every `{APP_ROOT}/...` placeholder in the rules, agents, commands, and skills expands to this value. All PHP code and tests live under `{APP_ROOT}/` (e.g., `{APP_ROOT}/app/`, `{APP_ROOT}/tests/`). NEVER create `app/` or `tests/` in the repo root. If a project keeps PHP at the repo root, set `CLAUDE_APP_ROOT` to any of: `""` (empty string), `"."`, `"./"`, or `"/"` — all are normalized to the repo root by `php-postwrite.sh`.
- **Workflow is Docker-only (WSL2).** PHP, Composer, Pint run inside `${CLAUDE_PHP_CONTAINER}`. `secret-guard.sh` and `php-postwrite.sh` run automatically. PHPStan and tests run via `/phpstan` and `/test` slash commands — do not invoke hooks manually.

## 1.5. Uncertainty Protocol
- State assumptions explicitly before implementing. If uncertain, ask — don't guess.
- If multiple valid interpretations exist, present them; never pick one silently.
- If a simpler approach exists, say so and argue for it. Push back when warranted.
- If something is genuinely unclear, stop, name what's confusing, and ask.

## 2. Tech Stack
- **PHP 8.4** (readonly, hooks, asymmetric visibility, #[\Override])
- **Laravel 13**, **PHPUnit 12** (installed) / **Pest 4** (preferred, requires `pestphp/pest`), **Pint**, **PHPStan L8** (auto-detect by `/phpstan`: runs only if installed)
- **DTO:** `readonly class` or `spatie/laravel-data`
- **DB:** PostgreSQL (pref), MySQL, SQLite (tests)
- **Pre-approved:** `spatie/` (laravel-data, permission, query-builder)
- **Banned:** Non-Eloquent ORMs, service locators, duplicated core features.

## 3. Workflow (See `.claude/rules/architecture.md`)
- **Modes:** FEATURE (ARCHITECT → IMPLEMENT → SELF-REVIEW → QA), FIX, REFACTOR, TEST.
- **Header:** `[MODE] [COMPLEX] [DDD]`.
- **Pipeline:** Max 3 self-review loops. Surface blockers if unresolved.

## 4. Rule Catalog
- **Architecture & Workflow:** `.claude/rules/architecture.md` (Workflow, Output rules)
- **Technical Stack:** `.claude/rules/technical_stack.md` (API, DB, Eloquent, Perf)
- **Quality & Security:** `.claude/rules/quality_gate.md` (Testing, Security, Review checklist)
- **Layer Context:** `.claude/rules/layers_context.md` (Domain, Application, Infrastructure, UI)
- **Advanced Patterns:** `.claude/rules/advanced_patterns.md` (Concurrency, Idempotency, Outbox, CQRS, Resilience)

## 5. Tooling
- **Skill:** `laravel-ddd-architect` (Staff-level DDD designer with DSL/generator) — see `.claude/skills/laravel-ddd-architect/`.
- **Agents (`.claude/agents/`):** `ddd-reviewer`, `code-reviewer`, `perf-auditor`, `security-auditor`, `module-scaffolder`, `test-writer`. Invoke proactively when their description matches the work.
- **Hooks (`.claude/hooks/`):** `secret-guard.sh` (PreToolUse Write|Edit|Bash) — host-side regex guard, blocks secret-shaped paths/payloads. `php-postwrite.sh` (PostToolUse Write|Edit, *.php) — host script that shells into `${CLAUDE_PHP_CONTAINER}` for Pint + `php -l`.
- **Slash commands:** `/phpstan` — PHPStan в контейнере (если установлен); `/test` — Pest / PHPUnit / artisan test в контейнере (auto-detect); `/composer-audit` — проверка CVE в зависимостях.

## 6. Constraints
- Match complexity to task. No "future-proofing".
- Fix root causes. No `TODO`s or drive-by formatting.
- Match existing code style even if you'd do it differently. Every changed line must trace to the user's request.
- Priority: **maintainability → testability → scalability → ergonomic shortcuts**.
