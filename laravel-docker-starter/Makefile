# Common tasks. Requires Docker with the compose plugin; PHP/Composer on the host are optional.
COMPOSER = docker run --rm -u "$$(id -u):$$(id -g)" -v "$$PWD":/app -w /app composer:2
PHP      = docker run --rm -u "$$(id -u):$$(id -g)" -v "$$PWD":/app -w /app php:8.3-cli-alpine

.PHONY: init up down logs ps test lint fresh

init: ## First run: install dependencies (creates composer.lock), create .env and APP_KEY
	$(COMPOSER) install --no-interaction --ignore-platform-reqs
	test -f .env || cp .env.example .env
	$(PHP) php artisan key:generate --ansi

up: ## Build and start the stack on http://localhost:8080
	docker compose up -d --build --wait
	@echo "Up: http://localhost:$${WEB_PORT:-8080}  (API: /api/tasks, health: /up)"

down: ## Stop the stack (keeps the database volume)
	docker compose down

fresh: ## Stop the stack and delete the database volume
	docker compose down -v

logs: ## Follow logs
	docker compose logs -f

ps: ## Container status
	docker compose ps

test: ## Run the test suite (SQLite in memory)
	$(PHP) php artisan test

lint: ## Check code style with Laravel Pint
	$(PHP) vendor/bin/pint --test
