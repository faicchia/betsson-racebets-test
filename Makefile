.DEFAULT_GOAL := help

COMPOSE := docker compose

# Without a terminal on stdin (git hooks, CI, pipes) docker must not allocate a TTY, or it fails with
# "the input device is not a TTY".
NO_TTY := $(shell [ -t 0 ] || echo -T)

EXEC := $(COMPOSE) exec $(NO_TTY) app

# Build the image with the host user's ids so files on the bind mount stay editable.
export UID := $(shell id -u)
export GID := $(shell id -g)

.PHONY: help
help: ## List available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

.PHONY: up
up: ## Build and start all containers in the background
	$(COMPOSE) up -d --build

.PHONY: down
down: ## Stop and remove all containers
	$(COMPOSE) down

.PHONY: build
build: ## Rebuild the app image
	$(COMPOSE) build app

.PHONY: ps
ps: ## List all containers
	$(COMPOSE) ps

.PHONY: restart
restart: down up ## Restart all containers

.PHONY: logs
logs: ## Follow the container logs
	$(COMPOSE) logs -f

.PHONY: shell
shell: ## Open a shell in the app container
	$(EXEC) bash

.PHONY: composer
composer: ## Run composer in the app container, e.g. make composer c="require foo/bar"
	$(EXEC) composer $(c)

.PHONY: console
console: ## Run bin/console in the app container, e.g. make console c="debug:router"
	$(EXEC) php bin/console $(c)

.PHONY: db-reset
db-reset: ## Wipe the database volume and recreate both databases from docker/mysql/schema.sql (deletes all data)
	$(COMPOSE) down -v
	$(COMPOSE) up -d --build

.PHONY: test
test: ## Run the test suite, e.g. make test c="--testsuite=Unit" or c="--filter=customer"
	$(EXEC) php vendor/bin/pest $(c)

.PHONY: lint
lint: ## Check the code style with PHP-CS-Fixer, showing what would change
	$(EXEC) php vendor/bin/php-cs-fixer check --diff

.PHONY: lint-fix
lint-fix: ## Fix the code style with PHP-CS-Fixer
	$(EXEC) php vendor/bin/php-cs-fixer fix

.PHONY: lint-config
lint-config: ## Run the linters: composer.json, YAML config and the service container
	$(EXEC) composer validate --strict --no-check-publish
	$(EXEC) php bin/console lint:yaml config --parse-tags
	$(EXEC) php bin/console lint:container

.PHONY: stan
stan: ## Run PHPStan static analysis
	$(EXEC) php vendor/bin/phpstan analyse --memory-limit=1G
