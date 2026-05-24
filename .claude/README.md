# Claude Configuration — Laravel DDD

Самодостаточная конфигурация Claude Code для проектов
Laravel 13 / PHP 8.4 с Clean Architecture, DDD, CQRS-lite и
high-load паттернами. Рассчитана на **Docker-only workflow в WSL2**:
весь PHP-инструментарий (Pint, PHPStan, Pest, artisan) запускается
внутри контейнера, на хосте PHP не нужен.

```
.claude/
├── CLAUDE.md                      Корневые инструкции (entry point)
├── README.md                      Этот файл
├── settings.json                  Хуки, права, общие env
├── settings.local.json            Локальные переопределения (контейнер) — в .gitignore
│
├── rules/                         Консолидированные правила:
│   ├── architecture.md            Workflow, Output Format
│   ├── technical_stack.md         API, БД, Eloquent, Performance
│   ├── quality_gate.md            Testing, Security, Review checklist
│   ├── layers_context.md          Cheat sheets по слоям (Domain, App, Infra, UI)
│   └── advanced_patterns.md       Concurrency, Idempotency, Outbox, CQRS, Resilience
│
├── commands/                      Slash-команды (/phpstan, /test, /composer-audit):
│   ├── phpstan.md                 PHPStan в контейнере
│   ├── test.md                    Pest / PHPUnit / artisan test в контейнере
│   └── composer-audit.md          composer audit — проверка CVE в зависимостях
│
├── skills/
│   └── laravel-ddd-architect/     Skill + DSL + генератор + stub-ы
│
├── agents/                        Спец-агенты (вызываются проактивно):
│   ├── code-reviewer.md           Naming, anti-patterns, API, tests, diff discipline
│   ├── ddd-reviewer.md            Только архитектура / DDD-нарушения
│   ├── module-scaffolder.md       Scaffold нового модуля
│   ├── perf-auditor.md            Только performance-hazards (N+1, unbounded queries…)
│   ├── security-auditor.md        Только security (SQLi, XSS, mass-assign, auth…)
│   └── test-writer.md             PHPUnit 12 / Pest 4 тесты для новых классов
│
└── hooks/                         Автоматические хуки (WSL2 + Docker):
    ├── _lib.sh                    Общие утилиты (extract_file_path + wslpath)
    ├── secret-guard.sh            PreToolUse Write|Edit|Bash — блокирует секреты
    └── php-postwrite.sh           PostToolUse Write|Edit — Pint + php -l
```

---

## Быстрый старт

1. **Прописать имя контейнера** в `settings.local.json`:

   ```json
   { "env": { "CLAUDE_PHP_CONTAINER": "shop_php" } }
   ```

   `settings.local.json` находится в `.gitignore` — не коммитится.
   Без этой переменной хуки молча скипают (не блокируют работу,
   но Pint не запускается).

2. **Опциональные env** (общие лежат в `settings.json`, локальные — в
   `settings.local.json`):

   - `CLAUDE_APP_ROOT` — **единственное место настройки корня приложения**:
     директория относительно корня репозитория, в которой лежит PHP-код
     и тесты (по умолчанию `src`). Все правила, агенты, команды и скиллы
     ссылаются на неё через плейсхолдер `{APP_ROOT}/`.
   - `CLAUDE_SRC_PREFIX` — явный путь на хосте в формате Unix/WSL
     (например, `/mnt/c/projects/...`), который маппится в `CLAUDE_CONTAINER_ROOT`.
     Переопределяет вычисление из `CLAUDE_APP_ROOT`
     (по умолчанию `<project_root>/${CLAUDE_APP_ROOT}/`).
   - `CLAUDE_CONTAINER_ROOT` — рабочая директория внутри контейнера
     (по умолчанию `/var/www/html`).
   - `CLAUDE_PHPSTAN_LEVEL`, `CLAUDE_PHPSTAN_MEMORY` — настройка PHPStan
     (level используется только если `phpstan.neon` его не задаёт).

3. **Запускать skill** упоминанием в диалоге:
   «используй skill laravel-ddd-architect, чтобы заскаффолдить модуль Order».

4. **Использовать агентов проактивно** по их описанию:

   - `code-reviewer` — после любого multi-file changeset
   - `ddd-reviewer` — после правок в `{APP_ROOT}/app/Domain/`, `{APP_ROOT}/app/Application/`,
     `{APP_ROOT}/app/Infrastructure/`, `{APP_ROOT}/app/Interface/`, `{APP_ROOT}/app/Modules/*`
   - `perf-auditor` — после изменений Eloquent / репозиториев / горячих путей
   - `security-auditor` — после правок контроллеров, Form Request, конфигов
   - `module-scaffolder` — создать новый модуль / bounded context
   - `test-writer` — добавить тесты для новых классов

---

## Slash-команды

| Команда | Что делает |
|---|---|
| `/phpstan` | PHPStan в контейнере. Уровень из конфига приоритетнее `CLAUDE_PHPSTAN_LEVEL`. |
| `/test` | Авто-детект: Pest (с `--parallel` если ≥ 3 или paratest), PHPUnit, artisan test. |
| `/composer-audit` | `composer audit` — список CVE в зависимостях. |

---

## Слои правил

`CLAUDE.md` — entry point. Тематические правила сгруппированы в `rules/`:

- `architecture.md` — режимы (FEATURE/FIX/REFACTOR/TEST), пайплайн
  (ARCHITECT → IMPLEMENT → SELF-REVIEW → QA), формат ответа.
- `technical_stack.md` — API, БД, Eloquent, performance-critical паттерны.
- `quality_gate.md` — тестирование, безопасность, чек-лист самопроверки.
- `layers_context.md` — cheat sheets по 4 слоям.
- `advanced_patterns.md` — concurrency, idempotency, outbox, CQRS, resilience.

---

## Режимы и формат ответа

Каждый ответ с кодом начинается со строки-заголовка:

```
[FEATURE | FIX | REFACTOR | TEST] [SIMPLE | MEDIUM | COMPLEX] [CRUD | Action+DTO | DDD]
```

Секции (опускаются, если неприменимы):

```
--- IMPLEMENTATION ---
--- SELF-REVIEW ---     (≤5 пунктов или OK)
--- FIX ---             (только если SELF-REVIEW нашёл проблемы)
--- QA ---              (добавленные/изменённые тесты)
```

`TEST` mode пропускает IMPLEMENTATION и SELF-REVIEW — сразу QA.

Подробности — `rules/architecture.md`.

---

## Хуки

Все хуки выполняются **в контексте WSL2 bash**. На хосте необходимы
`bash`, `docker`, `python3` (используется в `_lib.sh` для разбора JSON —
если нет Python, используется grep) (стандартный набор WSL2 Ubuntu).
Общие утилиты вынесены в `_lib.sh` (parses `tool_input.file_path`,
нормализует UNC-пути через `wslpath`).

| Событие | Хук | Что делает |
|---|---|---|
| `PreToolUse` (`Write\|Edit\|Bash`) | `secret-guard.sh` | Блокирует файлы и содержимое, попадающие под паттерны секретов (AWS, GCP, GitHub, GitLab, Stripe, Slack, HuggingFace, Doppler, JWT, PEM…). |
| `PostToolUse` (`Write\|Edit`, *.php) | `php-postwrite.sh` | Маппит хост-путь в контейнерный, прогоняет Pint + `php -l`. |

Если `CLAUDE_PHP_CONTAINER` не задан или контейнер не запущен —
хуки выходят с кодом 0 (не блокируют работу).
