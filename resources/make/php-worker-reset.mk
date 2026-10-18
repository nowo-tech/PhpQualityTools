# php-worker-reset — restart long-lived PHP workers (FrankenPHP worker mode, RoadRunner, Swoole…) after a
# front-end build, so no worker keeps serving stale Vite entrypoints / manifest data cached in memory.
#
# Shipped by nowo-tech/php-quality-tools (since 1.6.0). Docker Compose specific; every name is a variable.
#
# Usage in the host Makefile (after DC / service variables are defined):
#
#   PHP_WORKER_RESET_COMPOSE := $(DC)                      # e.g. docker compose -f compose.yaml
#   PHP_WORKER_RESET_EXTRA_COMPOSE := $(DC_E2E)            # optional second stack (E2E); reset only when it is up
#   PHP_WORKER_RESET_EXTRA_SERVICES := messenger messenger-notify   # optional (since 1.6.1): services sharing var/cache
#   -include vendor/nowo-tech/php-quality-tools/resources/make/php-worker-reset.mk
#
#   vite-build:
#   	$(DC) exec -T php pnpm run build
#   	@$(MAKE) php-worker-reset
#
# What it does, for the main stack and (when set and running) the extra stack:
#   1. `cache:clear --no-warmup` inside the PHP service (failures ignored: the restart is what matters);
#   2. `restart` of the PHP service (the container env is kept);
#   3. `restart` of every PHP_WORKER_RESET_EXTRA_SERVICES service (no cache:clear in them), after the PHP service:
#      workers sharing var/cache (e.g. Messenger consumers) must not keep the old container in memory and
#      rebuild the cache from it. In the extra stack a failed restart (service not defined there) is ignored;
#   4. waits until every container of those services reports healthy (containers without a healthcheck count
#      as healthy at once), at most PHP_WORKER_RESET_TIMEOUT seconds each.

PHP_WORKER_RESET_COMPOSE ?= docker compose
PHP_WORKER_RESET_EXTRA_COMPOSE ?=
PHP_WORKER_RESET_SERVICE ?= php
PHP_WORKER_RESET_EXTRA_SERVICES ?=
PHP_WORKER_RESET_CACHE_CLEAR ?= bin/console cache:clear --no-warmup
PHP_WORKER_RESET_TIMEOUT ?= 90

.PHONY: php-worker-reset

php-worker-reset:
	@$(PHP_WORKER_RESET_COMPOSE) exec -T $(PHP_WORKER_RESET_SERVICE) $(PHP_WORKER_RESET_CACHE_CLEAR) >/dev/null 2>&1 || true
	@$(PHP_WORKER_RESET_COMPOSE) restart $(PHP_WORKER_RESET_SERVICE) >/dev/null 2>&1
	@for s in $(PHP_WORKER_RESET_EXTRA_SERVICES); do $(PHP_WORKER_RESET_COMPOSE) restart $$s >/dev/null 2>&1 || { echo "php-worker-reset: restart of service $$s failed" >&2; exit 1; }; done
	@extra_up=0; \
	if [ -n "$(strip $(PHP_WORKER_RESET_EXTRA_COMPOSE))" ] && $(PHP_WORKER_RESET_EXTRA_COMPOSE) exec -T $(PHP_WORKER_RESET_SERVICE) true >/dev/null 2>&1; then \
		extra_up=1; \
		$(PHP_WORKER_RESET_EXTRA_COMPOSE) exec -T $(PHP_WORKER_RESET_SERVICE) $(PHP_WORKER_RESET_CACHE_CLEAR) >/dev/null 2>&1 || true; \
		$(PHP_WORKER_RESET_EXTRA_COMPOSE) restart $(PHP_WORKER_RESET_SERVICE) >/dev/null 2>&1; \
		for s in $(PHP_WORKER_RESET_EXTRA_SERVICES); do $(PHP_WORKER_RESET_EXTRA_COMPOSE) restart $$s >/dev/null 2>&1 || true; done; \
	fi; \
	ids="$$($(PHP_WORKER_RESET_COMPOSE) ps -q $(PHP_WORKER_RESET_SERVICE))"; \
	for s in $(PHP_WORKER_RESET_EXTRA_SERVICES); do ids="$$ids $$($(PHP_WORKER_RESET_COMPOSE) ps -q $$s)"; done; \
	if [ "$$extra_up" = 1 ]; then \
		ids="$$ids $$($(PHP_WORKER_RESET_EXTRA_COMPOSE) ps -q $(PHP_WORKER_RESET_SERVICE))"; \
		for s in $(PHP_WORKER_RESET_EXTRA_SERVICES); do ids="$$ids $$($(PHP_WORKER_RESET_EXTRA_COMPOSE) ps -q $$s 2>/dev/null)"; done; \
	fi; \
	for id in $$ids; do \
		i=0; \
		until [ "$$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}healthy{{end}}' $$id)" = healthy ] || [ $$i -ge $(PHP_WORKER_RESET_TIMEOUT) ]; do i=$$((i+1)); sleep 1; done; \
		if [ $$i -ge $(PHP_WORKER_RESET_TIMEOUT) ]; then echo "php-worker-reset: container $$id not healthy after $(PHP_WORKER_RESET_TIMEOUT)s" >&2; exit 1; fi; \
	done
	@echo "PHP workers restarted (cache cleared): fresh Vite entrypoints."
