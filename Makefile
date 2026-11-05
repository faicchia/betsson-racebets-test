.DEFAULT_GOAL := help

COMPOSE := docker compose
EXEC := $(COMPOSE) exec app

# Build the image with the host user's ids so files on the bind mount stay editable.
export UID := $(shell id -u)
export GID := $(shell id -g)

.PHONY: help
help: ## List available commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

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
