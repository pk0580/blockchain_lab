# blockchain-lab

Образовательная платформа для изучения блокчейнов L1/L2. Платежная инфраструктура промышленного уровня, которая одновременно служит «живым» учебником: каждая концепция (газ, мемпул, подтверждения, реорги, сырые транзакции, подпись, приватные ключи) соответствует конкретному модулю в коде, а уроки ссылаются на соответствующие классы.

В MVP поддерживаются: **Bitcoin** (regtest + testnet), **Ethereum Sepolia**, **Tron Shasta** и **Polygon Amoy**.

> Архитектура: модульный монолит на Laravel 13 (PHP 8.4) + изолированный сервис подписи на Go с использованием `trustwallet/wallet-core`.
> Структура: Module-First DDD в `src/app/Modules/{Context}/{Domain,Application,Infrastructure,UI}`.
> Стек данных: PostgreSQL 16 + Redis 7 + Horizon.

Актуальный план реализации см. в [`STEPS.md`](STEPS.md). Подробности архитектуры — в [`docs/architecture/`](docs/architecture/).

---

## Требования

- Docker + Docker Compose v2
- WSL2 (проект разрабатывается на Windows с WSL2; Linux / macOS также поддерживаются)
- `make`

## Быстрый старт

```bash
# 1. Скопируйте шаблон окружения
cp .env.example .env
# отредактируйте .env, установив UID/GID (команды `id -u` и `id -g` на хосте)

# 2. Соберите и запустите все сервисы
make up

# 3. Первичная настройка: установка Laravel в src/ и применение стабов проекта
make bootstrap

# 4. Проверка работоспособности
make test
make stan
```

После завершения `make bootstrap` откройте `http://localhost:${APP_PORT:-8000}/` — вы должны увидеть приветственную страницу Laravel.

## Полезные команды

```bash
make help            # список всех команд с описанием
make up / down       # запуск / остановка контейнеров
make shell           # bash внутри контейнера приложения
make test            # запуск тестов Pest
make stan            # запуск PHPStan (уровень 8)
make pint            # форматирование кода
make artisan c="..." # запуск любой команды artisan
make migrate         # запуск миграций базы данных
make fresh           # удаление + повторная миграция + сидинг
make audit           # composer audit (проверка безопасности)
```

Для PHPStan / тестов через слэш-команды Claude Code: `/phpstan`, `/test`, `/composer-audit`.

## Структура проекта

```
blockchain-lab/
├── .claude/                 ← агенты, хуки, правила, слэш-команды
├── docker/                  ← Dockerfile, nginx/, bitcoin/ конфиги
├── docs/architecture/       ← обзор + ADR (архитектурные решения)
├── stubs/                   ← заготовки, накладываемые на src/ через `make bootstrap`
├── src/                     ← приложение Laravel (создается при `make bootstrap`)
│   ├── app/Modules/{Ctx}/   ← ограниченные контексты (13 штук — см. STEPS.md §2)
│   ├── content/lessons/     ← образовательный слой (Phase 8)
│   └── tests/
│       ├── Architecture/    ← архитектурные тесты Pest (границы слоев)
│       ├── Unit/Domain/     ← тесты домена без фреймворка
│       ├── Integration/     ← тесты инфраструктуры с реальной БД
│       └── Feature/         ← функциональные HTTP тесты
├── docker-compose.yml
├── Makefile
├── STEPS.md                 ← план реализации
└── README.md                ← этот файл
```

## Что находится в `src/`?

После `make bootstrap` папка `src/` содержит чистый проект Laravel 13 с дополнениями:

- Стабы из `stubs/` (phpstan.neon, pint.json, архитектурные тесты, кастомный конфиг Pest).
- Дополнительные зависимости composer: Horizon, Sanctum, Spatie data/permission/query-builder, Pest 4 + Larastan + PHPStan.
- Дерево модулей под `app/Modules/`, которое растет по мере выполнения фаз проекта.

## Образовательный слой

Уроки находятся в `src/content/lessons/` (Фаза 8). Каждый урок:

1. Начинается с аналогии, понятной начинающему инженеру.
2. Переходит к точному описанию на уровне протокола.
3. Содержит ссылки на классы платформы, реализующие данную концепцию.

Интерактивные песочницы (Inertia.js + Vue 3, Фаза 8) используют те же API, что и платформа: генератор ключей, редактор и визуализатор сырых транзакций, трекер мемпула, **симулятор реоргов** (админ-кнопка принудительно вызывает `bitcoind invalidateblock`, и ученик в реальном времени видит переход `confirmed → orphaned → re-detected`).

## Безопасность

- Приватные ключи хранятся только в сервисе подписи на Go (Фаза 2). Laravel хранит только ссылки.
- Конвертное шифрование (KMS / мастер-ключ Vault, DEK для каждого сида).
- Заголовок `Idempotency-Key` обязателен для всех эндпоинтов вывода средств.
- Исходящие вебхуки с HMAC-подписью через паттерн Transactional Outbox.
- Очереди для каждой сети (bulkhead), чтобы сбой в одной сети не парализовал работу остальных.
- Хук `secret-guard.sh` блокирует случайные коммиты `.env`, `.pem`, `id_rsa` и известных паттернов API-ключей.

См. [`docs/architecture/decisions/0002-go-signing-service.md`](docs/architecture/decisions/0002-go-signing-service.md).

## Разработка

1. Выберите фазу из [`STEPS.md`](STEPS.md).
2. Используйте навык `laravel-ddd-architect` для проектирования.
3. Генерируйте код через агентов (`module-scaffolder`, `test-writer`), когда это уместно.
4. Выполняйте `make pint && make stan && make test` локально.
5. Открывайте PR; проверки безопасности / DDD / производительности настроены как агенты Claude Code.

## Лицензия

TBD.
