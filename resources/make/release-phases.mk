# release-phases — a multi-phase gate (e.g. release-check) with one log per phase and a per-phase summary, plus the
# coverage helpers that say where a failed gate needs work.
#
# Shipped by nowo-tech/php-quality-tools (since 1.9.0). release-phase / check-changed-src-tested run on the host
# (POSIX sh); coverage-gaps and slowest-tests run through QUALITY_PHP_EXEC (empty: on the host).
#
# Usage in the host Makefile:
#
#   RELEASE_PHASES := sync=release-sync-check tested-src=check-changed-src-tested check-fast quick=release-quick \
#   	php-coverage=test-coverage
#   RELEASE_PHASE_FLAGS_check-fast := -j2 --output-sync=target    # optional: make flags of one phase
#   RELEASE_PHASE_FLAGS_quick := -k -j2 --output-sync=target
#   QUALITY_PHP_EXEC := docker compose exec -T php                # optional: PHP in a container
#   -include vendor/nowo-tech/php-quality-tools/resources/make/release-phases.mk
#
#   release-check: release-phases
#
# Targets:
#   release-phases            run every RELEASE_PHASES entry (`<name>=<target>` or `<target>`) in order through
#                             bin/release-phase: output streamed and kept in $(RELEASE_PHASE_DIR)/<name>.log, one
#                             OK / FAIL line per phase in summary.txt, stops at the first failing phase and prints the
#                             summary (title RELEASE_PHASES_TITLE, default release-check).
#   check-changed-src-tested  hint: source classes changed since the last tag that no test names (COVERAGE_HINT_STRICT=1
#                             makes it fail).
#   coverage-gaps             files below 100 % of COVERAGE_GAPS_REPORT (default var/coverage/clover.xml; LCOV works).
#   test-slowest              slowest test files of SLOWEST_TESTS_JUNIT (default var/coverage/junit.xml), N=30 files.
# In a custom recipe, `$(call release_phase,<name>,<command>)` runs one phase the same way.

QUALITY_TOOLS_BIN ?= vendor/nowo-tech/php-quality-tools/bin
QUALITY_PHP_EXEC ?=
RELEASE_PHASES ?=
RELEASE_PHASES_TITLE ?= release-check
RELEASE_PHASE_DIR ?= var/quiet/release
RELEASE_PHASE ?= RELEASE_PHASE_DIR='$(RELEASE_PHASE_DIR)' RELEASE_PHASE_TITLE='$(RELEASE_PHASES_TITLE)' sh $(QUALITY_TOOLS_BIN)/release-phase
COVERAGE_HINT_STRICT ?=
COVERAGE_GAPS_REPORT ?= var/coverage/clover.xml
SLOWEST_TESTS_JUNIT ?= var/coverage/junit.xml

release_phase = $(RELEASE_PHASE) $(1) $(2)
_release_phase_name = $(firstword $(subst =, ,$(1)))
_release_phase_target = $(lastword $(subst =, ,$(1)))
define _release_phase_newline


endef

.PHONY: release-phases check-changed-src-tested coverage-gaps test-slowest

release-phases:
	@[ -n "$(strip $(RELEASE_PHASES))" ] || { echo "release-phases: set RELEASE_PHASES (e.g. RELEASE_PHASES := lint=cs-check tests=test)"; exit 2; }
	@$(RELEASE_PHASE) --reset
	$(foreach p,$(RELEASE_PHASES),@$(call release_phase,$(call _release_phase_name,$(p)),$(MAKE) --no-print-directory $(RELEASE_PHASE_FLAGS_$(call _release_phase_name,$(p))) $(call _release_phase_target,$(p)))$(_release_phase_newline))
	@$(RELEASE_PHASE) --summary

check-changed-src-tested:
	@sh $(QUALITY_TOOLS_BIN)/check-changed-src-tested $(if $(strip $(COVERAGE_HINT_STRICT)),--strict)

coverage-gaps:
	@$(QUALITY_PHP_EXEC) php $(QUALITY_TOOLS_BIN)/coverage-gaps $(COVERAGE_GAPS_REPORT)

test-slowest:
	@$(QUALITY_PHP_EXEC) php $(QUALITY_TOOLS_BIN)/slowest-tests $(SLOWEST_TESTS_JUNIT) $(or $(N),30)
