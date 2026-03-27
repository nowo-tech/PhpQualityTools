# Makefile for PHP Quality Tools
# Simplifies Docker commands for development

.PHONY: help ensure-up up down build shell install assets test test-coverage cs-check cs-fix rector rector-dry phpstan qa release-check composer-sync clean update validate setup-hooks

COMPOSER_BIN := /usr/bin/composer

# Default target
help:
	@echo "PHP Quality Tools - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start Docker container"
	@echo "  down          Stop Docker container"
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
	@if ! docker-compose exec -T php true 2>/dev/null; then 		echo "Starting container..."; 		docker-compose up -d --build; 		sleep 3; 		docker-compose exec -T php $(COMPOSER_BIN) install --no-interaction; 	fi

# Build Docker image only
build:
	docker-compose build

# Build and start container
up:
	@echo "Building Docker image..."
	docker-compose build
	@echo "Starting container..."
	docker-compose up -d
	@echo "Waiting for container to be ready..."
	@sleep 2
	@echo "Installing dependencies..."
	docker-compose exec -T php $(COMPOSER_BIN) install --no-interaction
	@echo "Container ready"

# Stop container
down:
	docker-compose down

# Open shell in container
shell: ensure-up
	docker-compose exec php sh

# Install dependencies
install: ensure-up
	docker-compose exec -T php $(COMPOSER_BIN) install --no-interaction

# No frontend assets in this bundle
assets:
	@echo "No frontend assets in this bundle."

# Run tests
test: ensure-up
	docker-compose exec php $(COMPOSER_BIN) test

# Run tests with coverage
test-coverage: ensure-up
	docker-compose exec php $(COMPOSER_BIN) test-coverage | tee coverage-php.txt
	sh ./.scripts/php-coverage-percent.sh coverage-php.txt

# Check code style
cs-check: ensure-up
	docker-compose exec -T php $(COMPOSER_BIN) cs-check

# Fix code style
cs-fix: ensure-up
	docker-compose exec -T php $(COMPOSER_BIN) cs-fix

# Apply Rector refactoring
rector: ensure-up
	docker-compose exec -T php $(COMPOSER_BIN) rector

# Run Rector in dry-run mode
rector-dry: ensure-up
	docker-compose exec -T php $(COMPOSER_BIN) rector-dry

# Run PHPStan static analysis
phpstan: ensure-up
	docker-compose exec -T php $(COMPOSER_BIN) phpstan

# Run all QA
qa: ensure-up
	docker-compose exec -T php $(COMPOSER_BIN) qa

# Validate composer and keep lock in sync
composer-sync: ensure-up
	docker-compose exec -T php $(COMPOSER_BIN) validate --strict
	docker-compose exec -T php $(COMPOSER_BIN) update --lock --no-interaction --no-install

# Full pre-release chain
release-check:
	@$(MAKE) ensure-up
	@$(MAKE) composer-sync
	@$(MAKE) cs-fix
	@$(MAKE) cs-check
	@$(MAKE) rector-dry
	@$(MAKE) phpstan
	@$(MAKE) test-coverage

# Update dependencies
update: ensure-up
	docker-compose exec -T php $(COMPOSER_BIN) update --no-interaction

# Validate composer.json
validate: ensure-up
	docker-compose exec -T php $(COMPOSER_BIN) validate --strict

# Clean vendor and cache
clean:
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf coverage
	rm -f coverage.xml
	rm -f coverage-php.txt
	rm -f .php-cs-fixer.cache

# Setup git hooks for pre-commit checks
setup-hooks:
	chmod +x .githooks/pre-commit
	git config core.hooksPath .githooks
	@echo "Git hooks installed"
