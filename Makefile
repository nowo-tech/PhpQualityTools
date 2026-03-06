# Makefile for PHP Quality Tools
# Simplifies Docker commands for development

.PHONY: help up down shell ensure-up install test test-coverage cs-check cs-fix rector rector-dry phpstan qa clean setup-hooks

# Default target
help:
	@echo "PHP Quality Tools - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start Docker container"
	@echo "  down          Stop Docker container"
	@echo "  shell         Open shell in container"
	@echo "  install       Install Composer dependencies"
	@echo "  test          Run PHPUnit tests"
	@echo "  test-coverage Run tests with code coverage"
	@echo "  cs-check      Check code style"
	@echo "  cs-fix        Fix code style"
	@echo "  rector        Apply Rector refactoring"
	@echo "  rector-dry    Run Rector in dry-run mode"
	@echo "  phpstan       Run PHPStan static analysis"
	@echo "  qa            Run all QA checks (cs-check + test)"
	@echo "  clean         Remove vendor and cache"
	@echo "  setup-hooks   Install git pre-commit hooks"
	@echo ""

# Ensure container is running (start if not). Used by install, shell, test, test-coverage, cs-check, cs-fix, qa.
ensure-up:
	@if ! docker-compose exec -T php true 2>/dev/null; then \
		echo "Starting container..."; \
		docker-compose up -d; \
		sleep 3; \
		docker-compose exec -T php composer install --no-interaction; \
	fi

# Build and start container
up:
	@echo "Building Docker image..."
	docker-compose build
	@echo "Starting container..."
	docker-compose up -d
	@echo "Waiting for container to be ready..."
	@sleep 2
	@echo "Installing dependencies..."
	docker-compose exec -T php composer install --no-interaction
	@echo "✅ Container ready!"

# Stop container
down:
	docker-compose down

# Open shell in container
shell: ensure-up
	docker-compose exec php sh

# Install dependencies
install: ensure-up
	docker-compose exec -T php composer install

# Run tests (no -T so TTY is allocated and PHPUnit shows colors in console)
test: ensure-up
	docker-compose exec php composer test

# Run tests with coverage (no -T so coverage is shown in console with colors)
test-coverage: ensure-up
	docker-compose exec php composer test-coverage

# Check code style
cs-check: ensure-up
	docker-compose exec -T php composer cs-check

# Fix code style
cs-fix: ensure-up
	docker-compose exec -T php composer cs-fix

# Apply Rector refactoring
rector: ensure-up
	docker-compose exec -T php composer rector

# Run Rector in dry-run mode
rector-dry: ensure-up
	docker-compose exec -T php composer rector-dry

# Run PHPStan static analysis
phpstan: ensure-up
	docker-compose exec -T php composer phpstan

# Run all QA
qa: ensure-up
	docker-compose exec -T php composer qa

# Clean vendor and cache
clean:
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf coverage
	rm -f coverage.xml
	rm -f .php-cs-fixer.cache

# Setup git hooks for pre-commit checks
setup-hooks:
	chmod +x .githooks/pre-commit
	git config core.hooksPath .githooks
	@echo "✅ Git hooks installed! CS-check and tests will run before each commit."
