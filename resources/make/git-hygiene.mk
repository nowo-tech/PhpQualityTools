# git-hygiene — no AI agent co-author trailers in commit messages (Cursor, Claude, Copilot, Codex, Gemini, …).
#
# Shipped by nowo-tech/php-quality-tools (since 1.7.0). Runs on the host: POSIX sh + git + awk, no PHP.
#
# Usage in the host Makefile:
#
#   GIT_HYGIENE_SINCE_FILE := .scripts/agent-coauthor-baseline   # optional: older history accepted as-is
#   GIT_HYGIENE_AGENTS := cursor,claude                          # optional (default: cursor,claude,copilot,codex,gemini)
#   -include vendor/nowo-tech/php-quality-tools/resources/make/git-hygiene.mk
#
#   release-check: check-no-agent-coauthor …
#
# Targets:
#   check-no-agent-coauthor   fail when a commit reachable from GIT_HYGIENE_REF (default HEAD; a range works) carries
#                             an agent trailer (bin/check-no-agent-coauthor);
#   install-git-hooks         install the commit-msg hook that strips them into GIT_HYGIENE_HOOKS_DIR (default .githooks)
#                             and set core.hooksPath (bin/install-git-hooks; GIT_HYGIENE_HOOK_FLAGS e.g. --force).

GIT_HYGIENE_BIN ?= vendor/nowo-tech/php-quality-tools/bin
GIT_HYGIENE_REF ?= HEAD
GIT_HYGIENE_AGENTS ?=
GIT_HYGIENE_SINCE_FILE ?=
GIT_HYGIENE_HOOKS_DIR ?= .githooks
GIT_HYGIENE_HOOK_FLAGS ?=

.PHONY: check-no-agent-coauthor install-git-hooks

check-no-agent-coauthor:
	@sh $(GIT_HYGIENE_BIN)/check-no-agent-coauthor $(if $(strip $(GIT_HYGIENE_AGENTS)),--agents '$(strip $(GIT_HYGIENE_AGENTS))') $(if $(strip $(GIT_HYGIENE_SINCE_FILE)),--since-file '$(strip $(GIT_HYGIENE_SINCE_FILE))') '$(GIT_HYGIENE_REF)'

install-git-hooks:
	@sh $(GIT_HYGIENE_BIN)/install-git-hooks --hooks-dir '$(GIT_HYGIENE_HOOKS_DIR)' $(if $(strip $(GIT_HYGIENE_AGENTS)),--agents '$(strip $(GIT_HYGIENE_AGENTS))') $(GIT_HYGIENE_HOOK_FLAGS)
