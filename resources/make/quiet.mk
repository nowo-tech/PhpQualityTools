# quiet — short output for long targets (humans and AI agents) and a fast PHPUnit pre-check on the changed tests.
#
# Shipped by nowo-tech/php-quality-tools (since 1.9.0). quiet-run / changed-tests run on the host (POSIX sh);
# PHPUnit and junit-failures run through QUALITY_PHP_EXEC (empty: on the host).
#
# Usage in the host Makefile:
#
#   QUALITY_PHP_EXEC := docker compose exec -T php                         # optional: PHP in a container
#   QUIET_JUNIT_CMD := $(QUALITY_PHP_EXEC) vendor/bin/junit-failures       # optional (default: php on the host)
#   QUIET_JUNIT_DIRS := var/coverage var/quiet                             # optional (default: quiet-run's)
#   -include vendor/nowo-tech/php-quality-tools/resources/make/quiet.mk
#
#   make quiet T=check-fast          # full log in var/quiet/check-fast.log, OK / FAIL summary on screen
#   make test-changed                # PHPUnit on the tests covering the changes since BASE (default: the last tag)
#
# Variables:
#   QUIET_LOG_DIR          log directory (default var/quiet)
#   TEST_CHANGED_PHPUNIT   PHPUnit command; --log-junit <file> and the test files are appended
#                          (default: vendor/bin/phpunit --no-coverage --no-progress). A reset step first, e.g.:
#                          sh -c 'sh scripts/phpunit-reset.sh >/dev/null && vendor/bin/phpunit --no-coverage "$$@"' sh

QUALITY_TOOLS_BIN ?= vendor/nowo-tech/php-quality-tools/bin
QUALITY_PHP_EXEC ?=
QUIET_LOG_DIR ?= var/quiet
QUIET_JUNIT_CMD ?=
QUIET_JUNIT_DIRS ?=
TEST_CHANGED_PHPUNIT ?= vendor/bin/phpunit --no-coverage --no-progress

.PHONY: quiet test-changed

quiet:
	@[ -n "$(T)" ] || { echo "usage: make quiet T=<target> [VAR=value …]"; exit 2; }
	@$(if $(strip $(QUIET_JUNIT_CMD)),QUIET_JUNIT_CMD='$(strip $(QUIET_JUNIT_CMD))') $(if $(strip $(QUIET_JUNIT_DIRS)),QUIET_JUNIT_DIRS='$(strip $(QUIET_JUNIT_DIRS))') \
		sh $(QUALITY_TOOLS_BIN)/quiet-run $(QUIET_LOG_DIR)/$(T).log -- $(MAKE) --no-print-directory $(T)

test-changed:
	@tests=$$(sh $(QUALITY_TOOLS_BIN)/changed-tests) || exit $$?; \
	if [ -z "$$tests" ]; then echo "test-changed: no test covers the changes since $${BASE:-the last tag}"; exit 0; fi; \
	echo "test-changed: $$(echo "$$tests" | wc -l | tr -d ' ') test files"; \
	mkdir -p $(QUIET_LOG_DIR); \
	$(QUALITY_PHP_EXEC) $(TEST_CHANGED_PHPUNIT) --log-junit $(QUIET_LOG_DIR)/test-changed.junit.xml $$(echo $$tests) > $(QUIET_LOG_DIR)/test-changed.phpunit.log 2>&1; rc=$$?; \
	tail -n 3 $(QUIET_LOG_DIR)/test-changed.phpunit.log; \
	$(QUALITY_PHP_EXEC) php $(QUALITY_TOOLS_BIN)/junit-failures $(QUIET_LOG_DIR)/test-changed.junit.xml || true; \
	exit $$rc
