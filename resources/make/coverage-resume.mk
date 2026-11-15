# coverage-resume — the php-coverage phase of a release gate with resume: after a coverage run, a later run reruns
# only the tests a small change can affect (a test fix, or a few source files changed inside function bodies) and
# merges them into the kept run with PHPUnit's per-test coverage data (bin/coverage-resume). Anything else (new,
# deleted or renamed files, configuration, dependencies, DI, more than 10 source files, another PHP / PHPUnit /
# php-code-coverage / driver) runs the full suite.
#
# Shipped by nowo-tech/php-quality-tools (since 1.13.0). The recipe runs on the host (POSIX sh, git for the file
# list); bin/coverage-resume runs through QUALITY_PHP_EXEC, in the same PHP environment as the tests.
#
# Usage in the host Makefile:
#
#   COVERAGE_RESUME_FULL := test-coverage-full     # full suite: --coverage-php $(COVERAGE_RESUME_COV)
#                                                  #   --log-junit $(COVERAGE_RESUME_JUNIT) --coverage-clover $(COVERAGE_RESUME_CLOVER)
#   COVERAGE_RESUME_FILES := test-coverage-files   # the test files of $(FILES): --coverage-php $(COVERAGE_RESUME_PART_COV)
#                                                  #   --log-junit $(COVERAGE_RESUME_PART_JUNIT)
#   COVERAGE_RESUME_OPTIONS := --always=tests/Gate/*   # optional: bin/coverage-resume options (see its --help)
#   QUALITY_PHP_EXEC := docker compose exec -T php     # optional: PHP in a container
#   -include vendor/nowo-tech/php-quality-tools/resources/make/coverage-resume.mk
#
#   RELEASE_PHASES := ... php-coverage=php-coverage-resume
#   release-check: release-phases
#   	@echo "release-check OK — $$(cat $(COVERAGE_RESUME_STAMP)) — safe to tag."
#
# Targets:
#   php-coverage-resume   plan; resume (FILES target, merge into the kept run) or the full suite (FULL target); the
#                         coverage gate (COVERAGE_RESUME_GATE) on the merged / full Clover report; keep the run for
#                         the next resume; print and stamp the mode ("coverage: full" or
#                         "coverage: resumed (k tests, m files)" in $(COVERAGE_RESUME_STAMP)).
#   coverage-resume-forget  drop the kept run (the next run is the full suite).
# FORCE_COVERAGE=1, NO_RESUME=1 or COVERAGE_RESUME=0 always run the full suite (nightly / pre-release CI).
# COVERAGE_RESUME_MAX_COMMITS=<n> (optional) also requires the kept run's commit to be an ancestor of HEAD at most n
# commits back (the content comparison already sees every change, committed or not; this adds a history rule).

QUALITY_TOOLS_BIN ?= vendor/nowo-tech/php-quality-tools/bin
QUALITY_PHP_EXEC ?=
COVERAGE_RESUME_DIR ?= var/coverage/resume
COVERAGE_RESUME_COV ?= var/coverage/coverage.cov
COVERAGE_RESUME_JUNIT ?= var/coverage/junit.xml
COVERAGE_RESUME_CLOVER ?= var/coverage/clover.xml
COVERAGE_RESUME_PART_COV ?= var/coverage/resume-part.cov
COVERAGE_RESUME_PART_JUNIT ?= var/coverage/resume-part.xml
COVERAGE_RESUME_FULL ?=
COVERAGE_RESUME_FILES ?=
COVERAGE_RESUME_GATE ?= $(QUALITY_PHP_EXEC) php $(QUALITY_TOOLS_BIN)/check-coverage-threshold $(COVERAGE_RESUME_CLOVER) 100 --metric=statements
COVERAGE_RESUME_OPTIONS ?=
COVERAGE_RESUME_GIT_FILES ?= 1
COVERAGE_RESUME_MAX_COMMITS ?=
COVERAGE_RESUME_BIN ?= $(QUALITY_PHP_EXEC) php $(QUALITY_TOOLS_BIN)/coverage-resume
COVERAGE_RESUME_STAMP = $(COVERAGE_RESUME_DIR)/stamp.txt

.PHONY: php-coverage-resume coverage-resume-forget

php-coverage-resume:
	@[ -n "$(COVERAGE_RESUME_FULL)" ] && [ -n "$(COVERAGE_RESUME_FILES)" ] || { echo "php-coverage-resume: set COVERAGE_RESUME_FULL and COVERAGE_RESUME_FILES (targets)"; exit 2; }
	@set -uf; dir='$(COVERAGE_RESUME_DIR)'; mkdir -p "$$dir"; opts="--state=$$dir $(COVERAGE_RESUME_OPTIONS)"; force=; \
	if [ -n "$(COVERAGE_RESUME_GIT_FILES)" ] && git rev-parse --git-dir >/dev/null 2>&1; then \
		git ls-files -co --exclude-standard > "$$dir/files.txt" && opts="$$opts --files-from=$$dir/files.txt"; \
	fi; \
	case "$(FORCE_COVERAGE)$(NO_RESUME)" in '') ;; *) force=--force-full ;; esac; \
	case "$(COVERAGE_RESUME)" in 0|no|off|false) force=--force-full ;; esac; \
	commit=$$(git rev-parse HEAD 2>/dev/null || true); \
	if [ -z "$$force" ] && [ -n "$(COVERAGE_RESUME_MAX_COMMITS)" ]; then \
		kept=$$(sed -n 's/.*"commit":"\([0-9a-f]\{7,\}\)".*/\1/p' "$$dir/state.json" 2>/dev/null); \
		if [ -z "$$kept" ] || ! git merge-base --is-ancestor "$$kept" HEAD 2>/dev/null \
			|| [ "$$(git rev-list --count "$$kept..HEAD")" -gt "$(COVERAGE_RESUME_MAX_COMMITS)" ]; then \
			echo "php-coverage: the kept run is not an ancestor of HEAD within $(COVERAGE_RESUME_MAX_COMMITS) commit(s) — full suite"; \
			force=--force-full; \
		fi; \
	fi; \
	plan=0; files=$$($(COVERAGE_RESUME_BIN) plan $$opts $$force) || plan=$$?; \
	case $$plan in 0) mode=resume ;; 3) mode=full ;; *) exit $$plan ;; esac; \
	status=passed; \
	if [ $$mode = resume ]; then \
		rm -f $(COVERAGE_RESUME_PART_COV) $(COVERAGE_RESUME_PART_JUNIT); \
		$(MAKE) --no-print-directory $(COVERAGE_RESUME_FILES) FILES="$$(echo $$files)" || status=failed; \
		merge=0; $(COVERAGE_RESUME_BIN) merge $$opts --coverage=$(COVERAGE_RESUME_PART_COV) --junit=$(COVERAGE_RESUME_PART_JUNIT) --clover=$(COVERAGE_RESUME_CLOVER) || merge=$$?; \
		case $$merge in \
			0) [ $$status = failed ] || $(COVERAGE_RESUME_GATE) || status=failed; \
				$(COVERAGE_RESUME_BIN) save $$opts --coverage=$$dir/merged.cov --junit=$(COVERAGE_RESUME_PART_JUNIT) --status=$$status --commit=$$commit || true ;; \
			3) mode=full; status=passed; $(COVERAGE_RESUME_BIN) plan $$opts --force-full >/dev/null 2>&1 || true ;; \
			*) echo "php-coverage: the resumed run left no data to merge"; exit 1 ;; \
		esac; \
	fi; \
	if [ $$mode = full ]; then \
		rm -f $(COVERAGE_RESUME_COV) $(COVERAGE_RESUME_JUNIT) $(COVERAGE_RESUME_CLOVER); \
		$(MAKE) --no-print-directory $(COVERAGE_RESUME_FULL) || status=failed; \
		if [ -f $(COVERAGE_RESUME_COV) ] && [ -f $(COVERAGE_RESUME_JUNIT) ]; then \
			[ $$status = failed ] || $(COVERAGE_RESUME_GATE) || status=failed; \
			$(COVERAGE_RESUME_BIN) save $$opts --coverage=$(COVERAGE_RESUME_COV) --junit=$(COVERAGE_RESUME_JUNIT) --status=$$status --commit=$$commit || true; \
		else \
			status=failed; echo "php-coverage: the full run left no $(COVERAGE_RESUME_COV) / $(COVERAGE_RESUME_JUNIT) — nothing kept"; \
			$(COVERAGE_RESUME_BIN) forget $$opts >/dev/null 2>&1 || true; \
		fi; \
	fi; \
	echo "php-coverage: $$status — $$(cat "$$dir/stamp.txt" 2>/dev/null)"; \
	[ $$status = passed ]

coverage-resume-forget:
	@$(COVERAGE_RESUME_BIN) forget --state=$(COVERAGE_RESUME_DIR)
