# Workflow

## Mode Detection

Detect from user intent. If unclear, ask once or default to `FEATURE`.

| Mode | Trigger words | Pipeline |
|---|---|---|
| `FEATURE` | add, implement, build, create | ARCHITECT → IMPLEMENT → SELF-REVIEW → QA |
| `FIX` | bug, broken, fails, wrong, crash | REPRODUCE → IMPLEMENT (minimal diff, root cause) → SELF-REVIEW (+regression test) |
| `REFACTOR` | refactor, clean up, rename, extract, simplify | PLAN → IMPLEMENT (no behavior change) → SELF-REVIEW (tests untouched or only moved) |
| `TEST` | add tests, cover, missing tests | QA only |

---

## Pipeline Steps

### ARCHITECT (FEATURE only)

- Determine complexity tier: **Simple / Medium / Complex** using signals from `.claude/skills/laravel-ddd-architect/instructions.md`.
- For Complex features, show the full folder tree before generating any files.
- State the rationale in 1–2 sentences.
- If the tier is ambiguous, ask the user once: list the rules/invariants you see and ask which tier fits.

### REPRODUCE (FIX only)

- Write a failing test that proves the bug exists before touching production code.
- If a test is not possible (environment-specific, infra-level), describe the reproduction steps verbatim.
- Commit the failing test separately so the fix is clearly isolated.

### IMPLEMENT

- One code block per file; file path on its own line above the block.
- Omit unchanged portions; show ≤ 3 lines of context around each change.
- No drive-by formatting changes mixed with logic changes.
- No `TODO` left for the reviewer — do it now or explain why not.

### SELF-REVIEW

- Run the checklist in `.claude/rules/quality_gate.md`.
- Report **at most 5 issues**, or the single word `OK`.
- Loop: fix → review, **at most 3 iterations**.
- After 3 loops without `OK`: surface the unresolved issue and stop.

### QA

- List every test added or modified: test name + what it covers.
- Every new behavior path has at least one test.
- Every bug fix has a regression test.
- Architecture tests (`arch()`) updated when new namespaces are introduced.

### TEST (standalone)

`TEST` mode skips directly to QA. No ARCHITECT or IMPLEMENT steps.

- Detect testing framework before writing: check `{APP_ROOT}/composer.json` for `pestphp/pest`, else PHPUnit 12.
- Mirror source layout under `{APP_ROOT}/tests/`:
  - Domain → `Unit/Domain/{Ctx}/`
  - Application → `Unit/Application/{Ctx}/`
  - Infrastructure → `Integration/Infrastructure/{Ctx}/`
  - Controller → `Feature/{Ctx}/`
- Use stubs in `.claude/skills/laravel-ddd-architect/Tests/`.
- Architecture tests in `{APP_ROOT}/tests/Architecture/` when new namespaces are introduced.
- Follow all QA rules above.

---

## General Constraints

- Never repeat the same file twice in one response.
- No pseudo-code. Production-ready only.
- No `// @phpstan-ignore` without a comment explaining the exception.
- No `sleep()` in tests; use Laravel fakes.
- Complexity matches the task — do not apply DDD to trivial CRUD.
# Output Format

Every response that produces code starts with a single header line:

```
[MODE] [COMPLEXITY] [ARCH]
```

Examples:
- `[FEATURE] [MEDIUM] [Action+DTO]`
- `[FIX] [SIMPLE] [CRUD]`
- `[REFACTOR] [COMPLEX] [DDD]`

---

## Full Template

```
[MODE] [COMPLEXITY] [ARCH]

Brief rationale: 1–2 sentences on why this tier and pattern.

--- IMPLEMENTATION ---

{APP_ROOT}/app/Path/To/FirstFile.php
```php
<?php
// full file content
```

{APP_ROOT}/app/Path/To/SecondFile.php
```php
<?php
// full file content
```

--- SELF-REVIEW ---

1. Specific issue description (or the single word: OK)

--- FIX ---

(Only present if SELF-REVIEW found issues. Show only changed portions.)

{APP_ROOT}/app/Path/To/File.php
```php
// ≤3 lines of context
// changed lines
// ≤3 lines of context
```

--- QA ---

{APP_ROOT}/tests/Feature/Order/CreateOrderTest.php — happy path, 422 on missing items, 401 unauthenticated
{APP_ROOT}/tests/Unit/Domain/Order/OrderTest.php — invariant: cannot pay a Draft order
```

---

## File Block Rules

- **One code block per file.** Never split a file across multiple blocks.
- **File path** on its own plain line above the block (not inside the code as a comment).
- **`declare(strict_types=1);`** at the top of every PHP file.
- **Full file content** for new files. For existing files being modified, show only the changed method/section with ≤ 3 lines of context.
- **Do not repeat** the same file twice in one response.
- **No pseudo-code.** Every snippet must be valid, runnable PHP.

## Content Rules

- No inline `// TODO` left for the reviewer.
- No `// @phpstan-ignore` without an explanation comment immediately after.
- No commented-out code blocks.
- Identifiers and comments in English even when the response prose is in Russian.

## Rationale Line

One to two sentences explaining:
- Why this complexity tier (not the next one up).
- The key architectural choice made (e.g., "Action + DTO because there is one side effect but no invariants").
