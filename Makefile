DC      = docker compose
PHP     = $(DC) exec php php bin/console

# ─────────────────────────────────────────────────────────
# Help (default target)
# ─────────────────────────────────────────────────────────

.PHONY: help
help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-22s\033[0m %s\n", $$1, $$2}' \
		| sort

# ─────────────────────────────────────────────────────────
# Dev stack
# ─────────────────────────────────────────────────────────

.PHONY: up down restart build logs shell

up: ## Start the dev stack (http://localhost:8080)
	$(DC) up -d

down: ## Stop and remove dev containers
	$(DC) down

restart: ## Restart the dev stack
	$(DC) restart

build: ## Rebuild the dev image (after Dockerfile changes)
	$(DC) build php

logs: ## Follow all container logs
	$(DC) logs -f

logs-php: ## Follow php container logs only (sync output lives here)
	$(DC) logs -f php

shell: ## Open a shell inside the php container
	$(DC) exec php sh

# ─────────────────────────────────────────────────────────
# Application
# ─────────────────────────────────────────────────────────

.PHONY: migrate user

migrate: ## Run pending database migrations
	$(PHP) doctrine:migrations:migrate --no-interaction

user: ## Create a user: make user name=admin pass=secret
	$(PHP) app:create-user $(name) $(pass)

# ─────────────────────────────────────────────────────────
# Composer
.PHONY: composer-install composer-require composer-update

composer-install: ## Install composer dependencies
	$(DC) exec php composer install

composer-require: ## Add a package: make composer-require pkg=vendor/name
	$(DC) exec php composer require $(pkg)

composer-update: ## Update composer dependencies
	$(DC) exec php composer update

# ─────────────────────────────────────────────────────────
# Testing
# ─────────────────────────────────────────────────────────

.PHONY: test test-verbose test-coverage

test: ## Run PHPUnit tests
	$(DC) exec php ./vendor/bin/phpunit

test-verbose: ## Run PHPUnit tests with verbose output
	$(DC) exec php ./vendor/bin/phpunit --verbose

test-coverage: ## Run PHPUnit tests with coverage report
	$(DC) exec php ./vendor/bin/phpunit --coverage-html var/coverage

# ─────────────────────────────────────────────────────────
# Symfony
# ─────────────────────────────────────────────────────────
# Symfony
# ─────────────────────────────────────────────────────────

.PHONY: cache-clear routes debug-container

cache-clear: ## Clear the Symfony cache
	$(PHP) cache:clear

routes: ## List all registered routes
	$(PHP) debug:router

debug-container: ## Search the DI container: make debug-container q=sync
	$(PHP) debug:container $(q)

# ─────────────────────────────────────────────────────────
# Production image
# ─────────────────────────────────────────────────────────

.PHONY: prod-build prod-push

prod-build: ## Build the production Docker image locally
	docker build -f docker/php/Dockerfile --target prod -t ghcr.io/niobedev/toral-house-dashboard:latest .

prod-push: ## Push the production image to GHCR (requires docker login ghcr.io)
	docker push ghcr.io/niobedev/toral-house-dashboard:latest
