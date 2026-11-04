# heavy — run heavy targets through the machine-wide lock on a shared machine (FIFO slots, one job per group, load and
# memory gates) and guard long suites against memory exhaustion.
#
# Shipped by nowo-tech/php-quality-tools (since 1.9.0). Runs on the host: POSIX sh, no PHP.
#
# Usage in the host Makefile:
#
#   HEAVY_LOCK_LABEL := my-app                    # optional (default: the directory name)
#   -include vendor/nowo-tech/php-quality-tools/resources/make/heavy.mk
#
#   test-coverage:
#   	$(MEMORY_GUARD) --kill 'docker compose exec -T php pkill -f paratest' -- \
#   		docker compose exec -T php vendor/bin/paratest --processes=$(HEAVY_PROCESSES) --max-batch-size=5 …
#
#   make heavy-test-coverage        # test-coverage holding a slot (waits its turn, its group and the gates)
#   make heavy-quiet T=release-check
#
# Targets and variables:
#   heavy-<target>     run `$(MAKE) <target>` through bin/heavy-lock run (acquire, run, always release). Nested
#                      heavy-* calls go straight through (DOCKER_HEAVY_LOCK_HELD). Without the lock: when HEAVY_LOCK is
#                      empty or HEAVY_SKIP is not empty (default: the CI variable, set by GitHub Actions / GitLab CI).
#                      Never dry-run it with `make -n` (make still runs the recursive $(MAKE) line and takes the lock).
#   heavy-status       slots and their groups, queue, load average and available memory.
#   HEAVY_PROCESSES    suggested process count for parallel suites on the shared machine (default 2).
#   MEMORY_GUARD       bin/memory-guard (stops the suite below MEMGUARD_MIN_MB of MemAvailable, default 4096).
# The slots live in DOCKER_HEAVY_LOCK_DIR (default /tmp/docker-heavy.lock, slot N in <dir>.N); DOCKER_HEAVY_SLOTS (4),
# load threshold DOCKER_HEAVY_MAXLOAD (10), DOCKER_HEAVY_MIN_MEM_MB (6144). The group is DOCKER_HEAVY_GROUP, else the
# git repository of the directory make runs in, else HEAVY_LOCK_LABEL: two heavy-* jobs of one repository never overlap.

QUALITY_TOOLS_BIN ?= vendor/nowo-tech/php-quality-tools/bin
HEAVY_LOCK ?= sh $(QUALITY_TOOLS_BIN)/heavy-lock
HEAVY_LOCK_LABEL ?= $(notdir $(CURDIR))
HEAVY_SKIP ?= $(CI)
HEAVY_PROCESSES ?= 2
MEMORY_GUARD ?= sh $(QUALITY_TOOLS_BIN)/memory-guard

.PHONY: heavy-status

heavy-status:
	@$(if $(strip $(HEAVY_LOCK)),$(HEAVY_LOCK) status,echo "heavy-status: HEAVY_LOCK is empty (no lock)")

heavy-%:
	@if [ -n "$(strip $(HEAVY_LOCK))" ] && [ -z "$(strip $(HEAVY_SKIP))" ] && [ -z "$$DOCKER_HEAVY_LOCK_HELD" ]; then \
		$(HEAVY_LOCK) run "$(HEAVY_LOCK_LABEL): $*$(if $(T), $(T))" $(MAKE) $*; \
	else \
		$(MAKE) $*; \
	fi
