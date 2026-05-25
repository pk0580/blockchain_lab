.DEFAULT_GOAL := help

APP_SERVICE      ?= app
APP_CONTAINER    ?= blockchain-lab-app-1
APP_ROOT         ?= src
CONTAINER_ROOT   ?= /var/www/html
COMPOSE          ?= docker compose
EXEC             := $(COMPOSE) exec -u app $(APP_SERVICE)

.PHONY: help
help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make <target>\n\nTargets:\n"} \
	      /^[a-zA-Z0-9_.-]+:.*?##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 }' \
	      $(MAKEFILE_LIST)

# ----------------------------------------------------------------------------
# Lifecycle
# ----------------------------------------------------------------------------
.PHONY: up down restart logs ps shell
up: ## Start all services
	@[ -f .env ] || cp .env.example .env
	$(COMPOSE) up -d --build

down: ## Stop all services
	$(COMPOSE) down

restart: ## Restart all services
	$(COMPOSE) restart

logs: ## Tail all service logs
	$(COMPOSE) logs -f --tail=100

ps: ## Show container status
	$(COMPOSE) ps

shell: ## Open a bash shell in the app container
	$(EXEC) bash

# ----------------------------------------------------------------------------
# Bootstrap: install Laravel into ./src and apply project stubs
# ----------------------------------------------------------------------------
.PHONY: bootstrap install-laravel apply-stubs
bootstrap: up install-laravel apply-stubs composer-install ## Initial project bootstrap
	@echo ""
	@echo "Bootstrap complete. Try: make test && make stan"

install-laravel: ## Create a fresh Laravel project in ./src (idempotent)
	@if [ -f $(APP_ROOT)/composer.json ]; then \
		echo "Laravel already installed in $(APP_ROOT)/ — skipping."; \
	else \
		echo "Installing Laravel 13 into $(APP_ROOT)/ ..."; \
		$(EXEC) sh -c 'set -e; \
			rm -rf /tmp/laravel-new; \
			composer create-project laravel/laravel /tmp/laravel-new \
				--prefer-dist --no-interaction --no-install; \
			cp -a /tmp/laravel-new/. /var/www/html/; \
			rm -rf /tmp/laravel-new'; \
	fi

apply-stubs: ## Copy project stubs (phpstan.neon, pint.json, arch tests) into src/
	$(COMPOSE) cp stubs/phpstan.neon $(APP_SERVICE):$(CONTAINER_ROOT)/phpstan.neon
	$(COMPOSE) cp stubs/pint.json    $(APP_SERVICE):$(CONTAINER_ROOT)/pint.json
	$(COMPOSE) cp stubs/tests/Pest.php $(APP_SERVICE):$(CONTAINER_ROOT)/tests/Pest.php
	$(EXEC) mkdir -p tests/Architecture
	$(COMPOSE) cp stubs/tests/Architecture/LayersTest.php          $(APP_SERVICE):$(CONTAINER_ROOT)/tests/Architecture/LayersTest.php
	$(COMPOSE) cp stubs/tests/Architecture/ModuleBoundariesTest.php $(APP_SERVICE):$(CONTAINER_ROOT)/tests/Architecture/ModuleBoundariesTest.php
	$(EXEC) mkdir -p app/Modules app/Domain app/Application app/Infrastructure app/Interface

composer-install: ## composer install + add required project deps
	$(EXEC) composer install --no-interaction --no-progress --prefer-dist
	$(EXEC) composer require \
		laravel/horizon \
		laravel/sanctum \
		spatie/laravel-data \
		spatie/laravel-permission \
		spatie/laravel-query-builder \
		--no-interaction
	$(EXEC) composer require --dev \
		pestphp/pest \
		pestphp/pest-plugin-laravel \
		pestphp/pest-plugin-arch \
		larastan/larastan \
		phpstan/phpstan \
		--no-interaction

# ----------------------------------------------------------------------------
# Quality gates
# ----------------------------------------------------------------------------
.PHONY: test stan pint pint-test audit
test: ## Run the test suite
	$(EXEC) ./vendor/bin/pest --colors=always

stan: ## Run PHPStan (level 8)
	$(EXEC) ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M

pint: ## Format PHP code (Pint)
	$(EXEC) ./vendor/bin/pint

pint-test: ## Check formatting without changing files
	$(EXEC) ./vendor/bin/pint --test

audit: ## Composer security audit
	$(EXEC) composer audit

# ----------------------------------------------------------------------------
# Laravel helpers
# ----------------------------------------------------------------------------
.PHONY: artisan migrate fresh tinker queue
artisan: ## Run an artisan command (e.g. make artisan c="route:list")
	$(EXEC) php artisan $(c)

migrate: ## Run database migrations
	$(EXEC) php artisan migrate

fresh: ## Drop all tables and re-run migrations + seeds
	$(EXEC) php artisan migrate:fresh --seed

tinker: ## Open Tinker REPL
	$(EXEC) php artisan tinker

queue: ## Run queue worker (foreground; Horizon handles prod queues)
	$(EXEC) php artisan queue:listen --tries=3

# ----------------------------------------------------------------------------
# Frontend — Inertia + Vue + Vite через node service
# ----------------------------------------------------------------------------
NODE_SERVICE     ?= node
NODE_CONTAINER   ?= blockchain-lab-node-1
NODE_EXEC        := $(COMPOSE) exec $(NODE_SERVICE)

.PHONY: assets-install assets-dev assets-build assets-shell
assets-install: ## npm install в node container'е
	$(NODE_EXEC) npm install

assets-dev: ## Запустить Vite dev server (HMR на http://localhost:5173)
	$(NODE_EXEC) sh -c 'npm run dev -- --host 0.0.0.0'

assets-build: ## Собрать prod-bundle в public/build/
	$(NODE_EXEC) npm run build

assets-shell: ## sh внутри node container'а
	$(NODE_EXEC) sh
