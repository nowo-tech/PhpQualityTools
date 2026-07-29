# Makefile for PHP Quality Tools
# Simplifies Docker commands for development

.PHONY: help ensure-up up down down-dev build shell install assets test test-coverage cs-check cs-fix rector rector-dry phpstan qa release-check composer-sync clean update validate setup-hooks check-no-cursor-coauthor strip-cursor-coauthor-from-history

COMPOSER_BIN := /usr/bin/composer
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
COMPOSE := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
SERVICE_PHP := php

# Default target
help:
	@echo "PHP Quality Tools - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start Docker container"
	@echo "  down          Stop Docker container"
	@echo "  down-dev      Stop root compose (dev) and remove orphans"
	@echo "  build         Build Docker image"
	@echo "  shell         Open shell in container"
	@echo "  install       Install Composer dependencies"
	@echo "  assets        No frontend assets in this bundle"
	@echo "  test          Run PHPUnit tests"
	@echo "  test-coverage Run tests with code coverage"
	@echo "  cs-check      Check code style"
	@echo "  cs-fix        Fix code style"
	@echo "  rector        Apply Rector refactoring"
	@echo "  rector-dry    Run Rector in dry-run mode"
	@echo "  phpstan       Run PHPStan static analysis"
	@echo "  qa            Run all QA checks (cs-check + test)"
	@echo "  release-check Run full pre-release validation chain"
	@echo "  composer-sync Validate composer and sync lock"
	@echo "  clean         Remove vendor and cache"
	@echo "  update        Update Composer dependencies"
	@echo "  validate      Validate composer.json"
	@echo "  setup-hooks   Install git pre-commit hooks"

# Ensure container is running (start if not).
ensure-up:
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then 		echo "Starting container..."; 		$(COMPOSE) up -d --build; 		sleep 3; 		$(COMPOSE) exec -T php $(COMPOSER_BIN) install --no-interaction; 	fi

# Build Docker image only
build:
	$(COMPOSE) build

# Build and start container
up:
	@echo "Building Docker image..."
	$(COMPOSE) build
	@echo "Starting container..."
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 2
	@echo "Installing dependencies..."
	$(COMPOSE) exec -T php $(COMPOSER_BIN) install --no-interaction
	@echo "Container ready"

# Stop container
down:
	$(COMPOSE) down

down-dev:
	$(COMPOSE) down --remove-orphans

# Open shell in container
shell: ensure-up
	$(COMPOSE) exec php sh

# Install dependencies
install: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) install --no-interaction

# No frontend assets in this bundle
assets:
	@echo "No frontend assets in this bundle."

# Run tests
test: ensure-up
	$(COMPOSE) exec php $(COMPOSER_BIN) test

# Run tests with coverage
test-coverage: ensure-up
	$(COMPOSE) exec php $(COMPOSER_BIN) test-coverage | tee coverage-php.txt
	sh ./.scripts/php-coverage-percent.sh coverage-php.txt

# Check code style
cs-check: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) cs-check

# Fix code style
cs-fix: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) cs-fix

# Apply Rector refactoring
rector: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) rector

# Run Rector in dry-run mode
rector-dry: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) rector-dry

# Run PHPStan static analysis
phpstan: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) phpstan

# Run all QA
qa: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) qa

# Validate composer and keep lock in sync
composer-sync: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) validate --strict
	$(COMPOSE) exec -T php $(COMPOSER_BIN) update --lock --no-interaction --no-install

# Full pre-release chain
release-check: check-no-cursor-coauthor
	@$(MAKE) ensure-up
	@$(MAKE) composer-sync
	@$(MAKE) cs-fix
	@$(MAKE) cs-check
	@$(MAKE) rector-dry
	@$(MAKE) phpstan
	@$(MAKE) test-coverage

# Update dependencies
update: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) update --no-interaction

# Validate composer.json
validate: ensure-up
	$(COMPOSE) exec -T php $(COMPOSER_BIN) validate --strict

# Clean vendor and cache
clean:
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf coverage
	rm -f coverage.xml
	rm -f coverage-php.txt
	rm -f .php-cs-fixer.cache

# Setup git hooks for pre-commit checks
check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

setup-hooks:
	@chmod +x .githooks/pre-commit 2>/dev/null || true
	@chmod +x .githooks/commit-msg 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "✅ Git hooks installed (.githooks — includes commit-msg for REQ-GIT-001)."

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh main
