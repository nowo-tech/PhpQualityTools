# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.8.1] - 2026-10-22](#181---2026-10-22)
- [[1.8.0] - 2026-10-21](#180---2026-10-21)
- [[1.7.0] - 2026-10-18](#170---2026-10-18)
- [[1.6.1] - 2026-10-18](#161---2026-10-18)
- [[1.6.0] - 2026-10-17](#160---2026-10-17)
- [[1.5.0] - 2026-10-16](#150---2026-10-16)
- [[1.4.0] - 2026-10-15](#140---2026-10-15)
- [[1.3.0] - 2026-10-14](#130---2026-10-14)
- [[1.2.0] - 2026-10-13](#120---2026-10-13)
- [[1.1.0] - 2026-10-13](#110---2026-10-13)
- [[1.0.21] - 2026-10-09](#1021---2026-10-09)
- [[1.0.20] - 2026-10-09](#1020---2026-10-09)
- [[1.0.19] - 2026-10-09](#1019---2026-10-09)
- [[1.0.18] - 2026-09-27](#1018---2026-09-27)
- [[1.0.17] - 2026-08-24](#1017---2026-08-24)
- [[1.0.16] - 2026-08-19](#1016---2026-08-19)
- [[1.0.15] - 2026-08-04](#1015---2026-08-04)
- [[1.0.14] - 2026-07-29](#1014---2026-07-29)
- [[1.0.13] - 2026-07-16](#1013---2026-07-16)
- [[1.0.12] - 2026-07-09](#1012---2026-07-09)
- [[1.0.11] - 2026-04-15](#1011---2026-04-15)
- [[1.0.10] - 2025-03-06](#1010---2025-03-06)
- [[1.0.9] - 2024-12-19](#109---2024-12-19)
- [[1.0.8] - 2024-12-19](#108---2024-12-19)
- [[1.0.7] - 2024-12-19](#107---2024-12-19)
- [[1.0.6] - 2024-12-19](#106---2024-12-19)
- [[1.0.5] - 2024-12-19](#105---2024-12-19)
- [[1.0.4] - 2024-12-19](#104---2024-12-19)
- [[1.0.3] - 2024-12-19](#103---2024-12-19)
- [[1.0.2] - 2024-12-18](#102---2024-12-18)
- [[1.0.1] - 2024-12-XX](#101---2024-12-xx)
- [[1.0.0] - 2024-12-11](#100---2024-12-11)

## [Unreleased]

## [1.8.1] - 2026-10-22

Wave-2 REQ compliance: full release-check chain with open-PR and Igor gates, Rector on PHP 8.2, pinned CI matrix. See [UPGRADING](UPGRADING.md#to-181).

### Changed

- `release-check` starts with `ensure-up` and runs `check-open-prs` (new `.scripts/check-open-prs.sh`) and `igor` (REQ-MAKE-002, REQ-CS-008, REQ-REL-003); Igor now also audits `src/Gate` and `src/Testing` (canonical excludes).
- Rector uses the PHP 8.2 sets (package floor) with at most 2 workers (REQ-CS-003, REQ-SF-001).
- CI: Symfony 8.0 cell dropped (end of maintenance, advisories) and every `symfony/*` dev package pinned to the cell minor (REQ-SF-002).
- `composer.json`: author e-mail `@nowo.tech`, keywords trimmed to 10 (REQ-PKG-004).
- Docs: coverage-ignore justification in README (REQ-TEST-003); AI-audit row in the 12.4.1 checklist (REQ-SEC-002).
- Rector PHP 8.2 findings applied (`readonly` value objects in `Gate/`); `@igor-ignore` reasons on the CLI gate and PHPUnit helper state (never worker services).
- `.scripts/check-no-cursor-coauthor.sh` accepts git worktrees (REQ-GIT-001).

[1.8.1]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.8.1

## [1.8.0] - 2026-10-21

Quiet gate runs and focused test runs. See [UPGRADING](UPGRADING.md#to-180).

### Added

- **`bin/quiet-run`** (POSIX sh + awk, runs on hosts without PHP): `quiet-run [options] <log> -- <command>` writes the command's full output to `<log>` (directory created; ANSI CSI / OSC / charset codes and carriage-return progress redraws stripped) and prints a summary: `OK <label> (<s>s)` plus the key result lines (`--summary` / `$QUIET_SUMMARY`, at most `--summary-lines` / `$QUIET_SUMMARY_LINES`, default 15), or `FAIL <label> (<s>s, exit <code>)`, the failed make targets, the failed PHPUnit tests of every `*junit*.xml` written during the run (`--junit-dir` / `$QUIET_JUNIT_DIRS`, default `var build` and the log's directory; `--junit-cmd` / `$QUIET_JUNIT_CMD`, e.g. through `docker compose exec`) and the last `--context` / `$QUIET_CONTEXT` (default 15) lines before the first error (`--error` / `$QUIET_ERROR`). `--label` / `$QUIET_LABEL`. Exits with the command's exit code (2 on a usage error). Generalises Symfony Beacon's `scripts/quiet-run.sh`.
- **`bin/junit-failures`** (`Gate\Junit\JunitFailuresCommand`): `junit-failures <junit.xml> [max] [lines]` (or `--max`, `--lines`) prints only the failed / errored tests of a PHPUnit / ParaTest JUnit log — `Class::method (file:line)` and the head of the message — with `--strip-prefix` (default the current directory). Exit 0 none failed (nothing printed), 1 failures listed, 2 usage error or missing / unreadable log. From Beacon's `scripts/junit-failures.php`.
- **`bin/changed-tests`** (POSIX sh + git): lists the test files changed since `--base` / `$BASE` (default the last tag) — committed, staged, working-tree and untracked — plus the test files naming (whole word) a changed or new source class; `--src` / `$CHANGED_TESTS_SRC` (default `src`), `--tests` / `$CHANGED_TESTS_DIRS` (default `tests`), `--suffix` / `$CHANGED_TESTS_SUFFIX` (default `Test.php`); works from any subdirectory. Exit 0 (also for an empty list), 2 on a usage or git error. From Beacon's `scripts/changed-tests.sh`.
- [Usage → Quiet runs and focused test runs](USAGE.md#quiet-runs-and-focused-test-runs-since-180) with `make quiet T=<target>` and `make test-changed` recipes.

[1.8.0]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.8.0

## [1.7.0] - 2026-10-18

Git hygiene for AI agent co-author trailers and two profiler-based PHPUnit helpers. See [UPGRADING](UPGRADING.md#to-170).

### Added

- **`bin/check-no-agent-coauthor`** (POSIX sh + git + awk, runs on hosts without PHP): fails when a commit reachable from a ref or in a range carries an AI agent attribution trailer (`Co-authored-by`, `Made-with`, `Generated-by`, `Generated-with`, `Assisted-by`, case-insensitive) naming one of `--agents` (default `cursor,claude,copilot,codex,gemini`; also `aider`, `devin`; `$AGENT_COAUTHOR_AGENTS`) or matching `--pattern` (`$AGENT_COAUTHOR_PATTERN`). `--since` / `--since-file` audit only `<sha>..<ref>` when the baseline is an ancestor (older history accepted as-is), `--message-file` checks one message (hooks, PR text). Exit 0 / 1 / 2 (usage or git error). Generalises Symfony Beacon's `check-no-cursor-coauthor.sh` + `check-no-claude-coauthor.sh`.
- **`bin/strip-agent-coauthor`**: commit-msg / prepare-commit-msg hook that removes those trailers (and the blank lines they leave at the end); human co-authors are kept.
- **`bin/install-git-hooks`**: copies the strip hook to `--hooks-dir` (default `.githooks`) as `commit-msg` (`--prepare-commit-msg` too), bakes `--agents` in, sets `core.hooksPath` (`--no-config` to skip); refuses to replace a foreign hook without `--force`.
- **`resources/make/git-hygiene.mk`**: `check-no-agent-coauthor` and `install-git-hooks` targets (`GIT_HYGIENE_REF`, `GIT_HYGIENE_AGENTS`, `GIT_HYGIENE_SINCE_FILE`, `GIT_HYGIENE_HOOKS_DIR`, `GIT_HYGIENE_HOOK_FLAGS`, `GIT_HYGIENE_BIN`).
- **`Testing\RouteQueryScalingTrait`**: route-level query counts from the profiler's `db` collector with a `KernelBrowser` — `measureRouteQueries()`, `assertRouteQueryBudget()` and `assertRouteQueriesIndependentOfRows($client, $path, $seed, small: 5, large: 50, rowMarker: …, tolerance: 0)` (seed `[0, small)`, unmeasured warm-up, measure, seed `[small, large)`, measure; the seed closure gets the ORM entity manager, flushed and cleared). From Beacon's duplicated `assertQueryCountIndependentOfRows()` / `measureQueries()`. Independent of `QueryBudgetTrait`; both can be used together.
- **`Testing\MissingTranslationsTrait`**: `collectMissingTranslations($client, $url)` returns the profiler's missing messages (`DataCollectorTranslator::MESSAGE_MISSING`, unique `locale` / `domain` / `id`) of one request; `assertNoMissingTranslations($client, $url, allowlist: [...])` fails listing them (`"locale domain: id"`, `"domain: id"` or `"id"` entries, trailing `*` prefix) and returns the allowlisted ones. From Beacon's `FormWidgetMissingTranslationsTest`.
- Dev dependencies `symfony/browser-kit` and `symfony/translation` (real-kernel tests of the traits).

[1.7.0]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.7.0

## [1.6.1] - 2026-10-18

php-worker-reset restarts PHP_WORKER_RESET_EXTRA_SERVICES (e.g. messenger workers) after php. See [UPGRADING](UPGRADING.md#to-161).

### Added

- **`PHP_WORKER_RESET_EXTRA_SERVICES`** in `resources/make/php-worker-reset.mk` (default empty = unchanged behaviour): space-separated Compose services restarted after the PHP service, in the main stack and in the extra stack when it is up, without `cache:clear`, then waited for like the PHP service (`healthy`, or at once without a healthcheck). For workers that share `var/cache` with PHP (e.g. Messenger consumers): a worker still holding the old container would rebuild the cache after the clear with unresolved `%parameters%` (e.g. `url_generating_routes.php`), causing intermittent 500s. A failed restart fails the target in the main stack and is ignored in the extra stack.

[1.6.1]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.6.1

## [1.6.0] - 2026-10-17

### Added

- **`bin/check-no-inline-svg`** (`Gate\Svg\NoInlineSvgCommand`): fails on `<svg` in templates (`templates/`, `.twig` by default) and on SVG markup strings (`<svg`, `<path`, `<circle`, `<rect`, `<polyline`, `<polygon`) in front-end scripts (`assets/`, `.ts` by default; `*.test.*`, `*.spec.*`, `*.d.ts`, `node_modules/` skipped), with line numbers; `--allow=<path>` for genuine non-icon SVG (a stale allow entry fails); `--templates`, `--scripts`, `--template-ext`, `--script-ext`, `--no-scripts`, `--root`. Ported from Symfony Beacon's `NoInlineSvgTemplatesTest` (OTHER REQ-ICON-001).
- **`bin/check-asset-budgets`** (`Gate\Assets\AssetBudgetCommand`, `AssetBudgetConfig`, `ViteManifest`): per-entry JS / CSS size budgets of a Vite production build (entry + statically imported chunks; lazy chunks not counted), raw and gzip sizes (`js`, `css`, `js_gzip`, `css_gzip`), from `config/quality/asset-budgets.{yaml,yml,json,php}` and/or `--budget=<entry>:<kind>=<size>` (`250kB`, `1.5KiB`, bytes); `OK  ` / `FAIL` lines. Ported from Beacon's `scripts/check-asset-budgets.mjs` (OTHER REQ-ASSETS-APP-002).
- **`bin/check-doc-links`** (`Gate\DocLinks\DocLinksCommand`, `MarkdownAnchors`): broken relative links and images in the tracked / new Markdown files (`git ls-files`, or a tree walk outside git), fenced and inline code skipped, `/root-relative` targets, nested brackets (`[![badge](img)](url)`), `--exclude`; opt-in `--check-anchors` checks `#fragments` against GitHub heading slugs, HTML anchors and `{#id}` heading attributes. Ported from Beacon's `scripts/check-doc-links.mjs` (OTHER REQ-DOCS-APP-001).
- **`resources/make/php-worker-reset.mk`**: Docker Compose Makefile snippet that clears the cache and restarts the PHP service (and an optional second stack, e.g. E2E, when it is up), then waits for `healthy`, so long-lived workers never serve stale Vite entrypoints after a build. Service names and commands are variables. Ported from Beacon's `php-worker-reset` target (OTHER REQ-ASSETS-APP-002 item 7).
- [Usage → Front-end and docs gates](USAGE.md#front-end-and-docs-gates-since-160) and spec [`specs/005-frontend-docs-gates`](../specs/005-frontend-docs-gates/spec.md).
- `make docs-links` in this repository (`check-doc-links --check-anchors`), part of `make release-check`.

### Fixed

- `docs/CONTRIBUTING.md`: table of contents entries pointing at headings inside a fenced example (found by `check-doc-links --check-anchors`).

### Changed

- `composer.json`: three new `bin` entries; `symfony/yaml` suggestion also covers the asset budgets config. Nothing added to `require`.

### Unchanged

- Composer plugin behaviour and the existing gates and helpers.

[1.6.0]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.6.0

## [1.5.0] - 2026-10-16

### Added

- **`Testing\Env\PidPathEnvVarProcessor`** (Symfony env var processor `pid_path`): appends `-<pid>` to a path (`%env(pid_path:default:app.test_dir:APP_TEST_DIR)%`) without mutating `$_ENV`, so each PHPUnit / ParaTest process gets its own marker files and scratch directories. Register it with the `container.env_var_processor` tag under `when@test`. Ported from Symfony Beacon (`App\Shared\Env\PidPathEnvVarProcessor`, Beacon spec 149 FR-003).
- **`bin/clean-stale-pid-paths`** (`Testing\Pid\StalePidPathsCommand`): removes files / directories matching `{pid}` patterns (`'/dev/shm/app-phpunit-{pid}.db*'`) whose process no longer exists (`/proc`, else `posix_kill`), keeping those of running processes; `--dry-run`, `--quiet`; exit 0 / 1 (removal failed) / 2 (usage). Replaces the shell loops of Beacon's `scripts/phpunit-reset.sh`.
- [Usage → Parallel PHPUnit isolation](USAGE.md#parallel-phpunit-isolation-since-150): registration, `default:` fallback, cleanup, and a ParaTest coverage gate recipe (`paratest --processes=N` + `check-coverage-threshold`).
- Spec [`specs/004-paratest-isolation`](../specs/004-paratest-isolation/spec.md).

### Changed

- `composer.json`: `bin/clean-stale-pid-paths`; `symfony/dependency-injection` and `brianium/paratest` suggested (nothing added to `require`).

### Unchanged

- Composer plugin behaviour and the existing gates (`check-coverage-threshold`, `check-module-boundaries`, `assert-latest-tag`, `assert-release-proof`), `QueryBudgetTrait`.

[1.5.0]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.5.0

## [1.4.0] - 2026-10-15

### Added

- **`bin/assert-release-proof`** (`Gate\Release\ReleaseProofCommand`): passes only when the annotated release tag records `release-check: OK <sha>` for its own commit (the local gate proof), with `--github-output` (`proof=true|false`) and an explicit, logged `--allow-skip` + `RELEASE_GATE=skip` override. Lets tag-triggered deploys stay gated by default while hosted CI is unavailable (Nowo REQ-RELEASE-003 item 6).

[1.4.0]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.4.0

## [1.3.0] - 2026-10-14

### Added

- **`bin/assert-latest-tag`** (Composer binary, plain PHP): exits 0 only when a tag (argument, default `GITHUB_REF_NAME`) is the highest semver release tag (`<prefix>MAJOR.MINOR.PATCH`, numeric order, pre-releases excluded), so a tag-triggered deploy of an older tag never rolls production back. Tags from `git tag --list` (optional `--fetch[=<remote>]`) or `--stdin`; `--prefix=` for other tag schemes; `--github-output` appends `deploy=true|false` and `latest=<tag>` to `$GITHUB_OUTPUT` (with a `::notice` when skipped) and exits 0. Exit 1 = not the latest (strict mode), 2 = usage / git / output error. Ported from Symfony Beacon's `deploy-on-tag.yml` guard step (KIT-EXTRACTION row #20).
- [Usage → Release guards](USAGE.md#release-guards-since-130): deploy workflow examples (guard job + self-hosted deploy job, single-job variant, `git ls-remote` variant) and the recommended local `make release-check` recipe (QA + coverage threshold + Gitleaks secrets scan) as the release gate while hosted CI is unavailable.
- Spec [`specs/003-release-guards`](../specs/003-release-guards/spec.md).

### Changed

- `bin/autoload.php`: when no Composer autoloader is found (bare `git clone` of the package), falls back to a PSR-4 loader for `src/` instead of exiting 2, so the dependency-free gates run without `composer install`.

### Unchanged

- Composer plugin behaviour; `check-coverage-threshold`, `check-module-boundaries` and `QueryBudgetTrait`.

[1.3.0]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.3.0

## [1.2.0] - 2026-10-13

### Added

- **`bin/check-coverage-threshold`** (Composer binary, plain PHP): reads a Clover report and fails (exit 1) when statement and/or line coverage is under a minimum (argument, `--min=N` or `COVERAGE_MIN`; informational exit 0 without one). `--metric=both|statements|lines`; exit 2 on usage / parse errors. Ported from Symfony Beacon's `.scripts/check-coverage-threshold.sh` (no `python3` fallback needed).
- **`bin/check-module-boundaries`** (Composer binary): checks a module-boundary map from `config/quality/module-boundaries.yaml` (`.yml` / `.php` or `--config`): `modules` (namespace prefix + path), `allowed_edges` (allow-list), `forbidden_edges` (deny-list with `except`), `ignore`, `references: use|all`, and extra `rules` (`forbid_path`, `require_path`, `forbid_import`, `forbid_pattern`, `require_pattern`, `require_near`). Token-based `use` detection (grouped imports, `use function` / `use const`). Exit 0 / 1 (violations) / 2 (config error). Expresses every rule of Beacon's `.scripts/check-module-boundaries.sh` (see `tests/Fixtures/module-boundaries/beacon.yaml`).
- **`Testing\QueryBudgetTrait`** (PHPUnit): `recordQueries()`, `countQueries()`, `assertQueryBudget(int $max, callable)` and `assertScalesWith($prepare, $operation, $sizes, $tolerance)` (anti N+1: the query count must not grow with N), fed by **`Testing\Dbal\QueryCountingMiddleware`** (Doctrine DBAL 4 middleware) and `Testing\QueryCounter`.
- Spec [`specs/002-gate-scripts`](../specs/002-gate-scripts/spec.md).

### Changed

- `composer.json`: `bin` entries; `symfony/yaml`, `doctrine/dbal` (^4) and `phpunit/phpunit` suggested (dev-only, nothing added to `require`); `doctrine/dbal` and `symfony/yaml` in `require-dev`; `dev-main` branch alias `1.x-dev`.

### Unchanged

- Composer plugin behaviour (config copy, dependency suggestions, script injection).

[1.2.0]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.2.0

## [1.1.0] - 2026-10-13

### Fixed

- `rector/rector-symfony`, `rector/rector-doctrine` and `rector/rector-phpunit` (with `^1.0` / `^0.16` constraints) are no longer suggested when Rector is not installed: Rector absent (or with an unparseable version) now counts as Rector 2.x, which bundles those rule sets. They are only suggested when Rector 1.x is installed.
- The `twig:fix:check` Composer script added by the plugin no longer passes `--fix` (it modified templates); it is now a read-only `twig-cs-fixer lint --config=.twig-cs-fixer.php`.
- `Plugin` class docblock: missing configuration files are created on both `composer install` and `composer update` (it said install only).

### Dependencies

- `actions/checkout` 6 → 7 (GitHub Actions; #56).
- `codecov/codecov-action` 5 → 7 (GitHub Actions).

[1.1.0]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.1.0

## [1.0.21] - 2026-10-09

### Fixed

- **Tests:** the PHPUnit suite no longer runs a real `composer require --dev --with-all-dependencies …` in the repository root. Several dependency-installation tests used to spawn Composer against this package's own `composer.json` / `composer.lock` (network access, ~45 s, and a successful resolution rewrote the files — the root cause of 1.0.19 shipping a `composer.json` out of sync with `composer.lock`).
- **Tests:** `Composer\Config::get()` mock maps used `null` instead of the default flags (`0`), so `vendor-dir` / `bin-dir` silently resolved to `null` and temp-dir fixtures were never read.

### Changed

- **Plugin (internal):** `installDependencies()` now delegates execution to an internal `Process\CommandRunnerInterface` (default `Process\ExecCommandRunner`, same `exec()` + `2>&1` behaviour). `Plugin` accepts an optional runner in its constructor; Composer still instantiates it without arguments. The executed command and console messages are unchanged.
- **Plugin (internal):** Rector optional-package constraint mapping (`rector-doctrine:^0.16`, `rector-symfony:^1.0`, `rector-phpunit:^1.0`) deduplicated into one helper; Rector `IfToNullCoalescingAssignRector` applied.

### Added

- **Tests:** `Tests\Support\RecordingCommandRunner` (asserts the exact escaped `composer require` command) and `Tests\Support\RepositoryIntegrityExtension` (PHPUnit extension: exits 1 if the suite modifies `composer.json` / `composer.lock`).
- **CI:** coverage job runs `git diff --exit-code -- composer.json composer.lock` after the suite.

[1.0.21]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.0.21

## [1.0.20] - 2026-10-09

### Fixed

- **CI:** `rector/rector` require-dev constraint set to `^2.7` so `composer.json` matches the committed `composer.lock` content-hash (`composer validate --strict` failed on 1.0.19).

[1.0.20]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.0.20

## [1.0.19] - 2026-10-09

### Fixed

- **PHPStan 2:** `SplitLongMethodCallRector::calculateArgumentsLength()` docblock accepts `Node\ArgPlaceholder` (PHP-Parser 5 / PHPStan 2 `MethodCall::$args` type).

### Changed

- **CI:** GitHub Actions bumped (`actions/cache` v6, `actions/github-script` v9, `actions/stale` v11).

### Dependencies

- `igor-php/igor-php` require-dev constraint bumped to `^0.10.0`; `nowo-tech/phpstan-frankenphp` 1.2.x Dependabot bumps.
- Dev lock refreshed: Symfony 7.4.20, PHPStan 2.3.1, Rector 2.7.0, PHPUnit 11.5.57, `nowo-tech/phpstan-frankenphp` 1.2.3.

[1.0.19]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.0.19

## [1.0.18] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.0.18]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.0.18

## [1.0.17] - 2026-08-24

### Changed

- Raise minimum PHP to **8.2** and sync README badge (REQ-SF-001).
- **QA:** add `phpstan-frankenphp` extension (REQ-CS-005).
- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.0.17]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.0.17

## [1.0.16] - 2026-08-19

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

### Changed

- **Dependencies (dev):** `rector/rector` 2.6.2, `nowo-tech/phpstan-frankenphp` 1.1.0, `phpstan/phpstan` group bump.

## [1.0.15] - 2026-08-04

### Fixed
- **CI:** apply php-cs-fixer 3.95 style on PhpCsFixer rules + Rector coverage test; skip `AddParamTypeSplFixedArrayRector` (conflicts with `fully_qualified_strict_types`).

[1.0.15]: https://github.com/nowo-tech/PhpQualityTools/releases/tag/v1.0.15

## [1.0.14] - 2026-07-29

### Added

- FrankenPHP Friendly Worker Mode banner in README (REQ-DOCS-017).
- `make down-dev` (REQ-MAKE-007).
- PHPStan step in CI `code-style` job (REQ-CS-006).
- Packagist keyword `composer-plugin` (REQ-PKG-004).
- **REQ-CS-005:** `nowo-tech/phpstan-frankenphp` in `require-dev` with classic + worker rulesets in `phpstan.neon.dist`.

### Changed

- `Plugin` is `final` (REQ-PHP-001).
- PHPStan: `ignoreErrors: []`, baseline removed; fixed CustomRulesSet / coverage-test findings (REQ-CS-006).
- `docs/CUSTOM_RULES.md` translated to English (REQ-DOCS-016).

### Documentation

- [UPGRADING.md](UPGRADING.md), [RELEASE.md](RELEASE.md) updated for 1.0.14.

## [1.0.13] - 2026-07-16

### Fixed

- **Composer script opt-in flow**: `installComposerScripts()` now reads and parses `composer.json` once before evaluating `auto_add_scripts`, so unreadable or invalid manifests report `Failed to read` / `Failed to parse` instead of being treated as opt-in disabled.
- **Non-file `composer.json`**: Paths that exist but are not regular files (e.g. a directory named `composer.json`) are rejected with a clear error; tests no longer rely on `chmod 000` (unreliable under root in CI/containers).
- **CI Symfony matrix**: Override `platform.php` with the runner’s real PHP version and require `symfony/framework-bundle` with `--dev`, so Symfony 7.4+ / 8.x resolve correctly despite `config.platform.php` = `8.1.99` in this package.

### Added

- **REQ-GIT-001**: Scripts and hooks to keep Cursor `Co-authored-by` trailers out of git history (`check-no-cursor-coauthor`, `commit-msg` hook, CI `git-hygiene` job).
- **Code of Conduct**: Contributor Covenant at repository root; linked from README and CONTRIBUTING.
- **docs/GITHUB_CI.md**: Operator reference for GitHub Actions / REQ-GIT-001.

### Improved

- **Dev dependencies**: `rector/rector` raised to `^2.5`; Twig-CS-Fixer / Composer / PHPStan bumps via Dependabot and lock refresh.
- **Makefile**: `release-check` depends on `check-no-cursor-coauthor`; `setup-hooks` installs `commit-msg`.
- **Code style**: PHP CS Fixer cleanups in `Plugin.php` and coverage tests.

### Documentation

- **UPGRADING.md**: Section for 1.0.13; historical pre-release script notes clarified vs opt-in since 1.0.12.
- **README**: Links to GitHub CI requirements and Code of Conduct; compatibility table through 1.0.13.
- **RELEASE.md**: Reminder to re-run Cursor co-author check after the release commit.

## [1.0.12] - 2026-07-09

### Changed

- **Composer script injection is opt-in**: The plugin no longer adds quality scripts to `composer.json` by default. Set `"extra": { "php-quality-tools": { "auto_add_scripts": true } }` in your project to restore automatic script installation (`fix`, `rector`, `twig:*`, etc.).

### Added

- **Symfony 8 support**: CI validates PHP 8.2–8.5 against Symfony 7.4, 8.0, and 8.1; README compatibility table and badges updated accordingly.
- **Repository maintenance**: `.cursorignore`, Scrutinizer CI config, Dependabot dependency groups, PR title lint, stale issue/PR automation, and CodeRabbit workflow.
- **Spec-driven development docs**: `docs/SPEC-DRIVEN-DEVELOPMENT.md` and `docs/SPEC-KIT.md` with baseline inventory under `specs/001-baseline/`.

### Improved

- **CI matrix**: Replaced single PHP-only matrix with PHP × Symfony combinations aligned to supported Symfony minors.
- **Dev dependency constraints**: Pinned `friendsofphp/php-cs-fixer` (^3.95), `rector/rector` (^2.4), and `vincentlanglet/twig-cs-fixer` (^3.14) in this package’s `composer.json`.
- **Makefile**: Added `update-deps` target via shared Nowo bundle script include.

### Documentation

- **README**: Composer Scripts section documents opt-in `auto_add_scripts`; Symfony 8 compatibility reflected in badges and table.
- **CONFIGURATION.md** / **INSTALLATION.md**: Composer script opt-in documented.
- **CONTRIBUTING.md**: Corrected clone URL to `nowo-tech/PhpQualityTools`.
- **ENGRAM.md**: Cross-link to spec-driven development guide.

## [1.0.11] - 2026-04-15

### Fixed

- **Composer `test` script** (this package): `composer test` now runs `phpunit --colors=always` instead of passing `color=always` as a bogus test path (which broke the test command).
- **Composer plugin scripts**: Generated `composer.json` scripts now use `--config=` / `-c` pointing to **`.php-cs-fixer.php`**, **`.rector.php`**, and **`.twig-cs-fixer.php`** (and Blade scripts use `--config=.php-cs-fixer.php` with the same env flags as `fix`), matching the files installed by the plugin. Previously, commands referenced `*.dist.php` filenames that were never installed, breaking `composer fix` / `composer rector` / Twig scripts on a default setup.

### Improved

- **Tests and coverage**: PHPUnit suite reorganized under `tests/Unit` and `tests/Integration`; line/branch coverage for PHP source brought to **100%** (PCOV), with README badge and “Tests and coverage” section updated accordingly.
- **Tooling**: Makefile and CI workflows aligned with the test layout and quality checks where applicable.

### Documentation

- **README**: “Updating” section corrected — missing config files are created on **both** `install` and `update`; removed the incorrect claim that new configs are only created on install. **Manual scripts** JSON updated to use the same config filenames as the plugin.
- **UPGRADING.md**: **Upgrading to 1.0.11** section for script/config filename alignment; historical 1.0.7 script notes clarified vs installed filenames.

## [1.0.10] - 2025-03-06

### Fixed

- **PHPStan level 8**: Resolved all 43 PHPStan errors for strict static analysis compliance
  - Added proper type annotations for iterable/array parameters and return types (`@param array<string>`, `@return array<string, bool>`, etc.)
  - Validated `file_get_contents()` and `json_decode()` results before use (string|false handling) in Plugin and tests
  - Replaced `isset($scripts['test'])` with `array_key_exists('test', $scripts)` for correct offset checks in Plugin
  - Rector rules: removed obsolete `Node\Scalar\Bool_` reference (PHP-Parser 5 uses `ConstFetch` for bool); added PHPDoc/ignores for `removeNode()` and `VariadicPlaceholder` types; relaxed `@param` to `Node` where needed for PHPStan
  - Tests: added `assertNotFalse()` for file_get_contents results, `ReflectionNamedType` for getReturnType(), and PHPDoc `array<int, mixed>` for test helpers

### Improved

- Static analysis and type safety across `src/` and `tests/` with zero PHPStan errors at level 8

## [1.0.9] - 2024-12-19

### Changed

- **Rector configuration file naming**: Changed from `rector.php` to `.rector.php` for consistency
  - All Rector configuration files now use dot prefix (`.rector.php`, `.rector.custom.php`)
  - Maintains consistency with other configuration files (`.php-cs-fixer.php`, `.twig-cs-fixer.php`)
  - Scripts updated to reference `.rector.dist.php` configuration file
  - Updated all documentation, tests, and examples to reflect new naming convention

### Fixed

- Fixed inconsistency in configuration file naming across all tools
- All configuration files now follow consistent naming pattern with dot prefix

## [1.0.8] - 2024-12-19

### Added

- **Project custom fixers support**: PHP-CS-Fixer configuration now supports project-specific custom fixers
  - Add your own custom fixers via `project_custom_fixers` array in `.php-cs-fixer.custom.php`
  - Project fixers are registered in addition to PHP Quality Tools fixers
  - Allows extending functionality with project-specific code style rules
  - Example: `'project_custom_fixers' => [\App\Fixer\Custom\MyCustomFixer::class]`

### Improved

- **PHP-CS-Fixer configuration**: Enhanced custom configuration file
  - Added `cache_file` option in `.php-cs-fixer.custom.php` for custom cache location
  - Changed `line_ending` from hardcoded `"\n"` to `PHP_EOL` for better cross-platform compatibility
  - Improved code formatting and documentation in custom configuration files

### Fixed

- Fixed indentation issue in `.php-cs-fixer.custom.php` documentation comments

## [1.0.7] - 2024-12-19

### Changed

- **Composer scripts updated**: Improved script names and commands for better consistency
  - **PHP-CS-Fixer scripts**: Renamed from `cs-check`/`cs-fix` to `fix:check`/`fix`
    - Added `PHP_CS_FIXER_IGNORE_ENV=1` environment variable to ignore environment-specific config
    - Added `--allow-risky=yes` flag for risky rules
    - Scripts now reference `.php-cs-fixer.dist.php` configuration file
  - **Rector scripts**: Renamed from `rector:fix` to `rector:check` for clarity
    - `rector` now applies changes (removed dry-run)
    - `rector:check` is the dry-run version
    - Scripts now reference `.rector.dist.php` configuration file
  - **Twig-CS-Fixer scripts**: Renamed and expanded script names
    - `twig-check` → `twig:lint` (dry-run linting)
    - `twig-fix` → `twig:fix` (apply fixes)
    - Added `twig:fix:check` (check with fix option)
    - All scripts now reference `.twig-cs-fixer.dist.php` configuration file
    - Better namespace-style naming consistency with colons (`:`) separator

### Improved

- Script naming follows a more consistent pattern with namespace-style separators (`:`)
- Scripts use `.dist.php` suffix to indicate distribution configuration files
- PHP-CS-Fixer scripts ignore environment-specific configurations for consistent behavior

## [1.0.6] - 2024-12-19

### Fixed

- **JSON format preservation**: Plugin now preserves the original indentation format when adding scripts to `composer.json`
  - Detects original indentation (2 spaces, 4 spaces, or tabs)
  - Maintains the original format instead of forcing 4-space indentation
  - Prevents unnecessary formatting changes to `composer.json`
- **Scripts order preservation**: Plugin now preserves the original order of existing scripts in `composer.json`
  - New scripts are added at the beginning of the scripts section (instead of being sorted alphabetically)
  - Existing scripts maintain their original order (no alphabetical reordering)
  - Respects your preferred script organization
- **Configuration files installation**: Configuration files are now copied during both `composer install` and `composer update`
  - Previously, files were only copied during `composer install`
  - Now files are created automatically on first installation, regardless of command used
  - Existing files are still never overwritten (preserves customizations)
- **Duplicate scripts prevention**: Added robust duplicate detection when adding scripts
  - Uses `array_key_exists()` instead of `isset()` for more reliable duplicate detection
  - Double-checks scripts before merging to prevent any duplicates
  - Ensures scripts are never added if they already exist in `composer.json`

### Added

- **JSON indentation detection**: New method `detectJsonIndentation()` to detect original JSON formatting
- **JSON formatting preservation**: New method `encodeJsonWithIndentation()` to maintain original formatting
- **Test coverage**: Added `JsonFormatPreservationTest.php` with comprehensive tests for JSON format preservation

### Improved

- Better user experience when installing/updating the package
- Configuration files are automatically available after installation or update

## [1.0.5] - 2024-12-19

### Fixed

- **Rector 2.x compatibility**: Fixed compatibility issues with optional Rector packages
  - Plugin now detects Rector version and handles optional packages accordingly
  - **For Rector 1.x**: Optional packages (`rector-symfony`, `rector-doctrine`, `rector-phpunit`) are installed normally
  - **For Rector 2.x**: Optional packages are automatically skipped (not compatible with Rector 2.x yet)
  - These packages (`rector-symfony:^1.0`, `rector-doctrine:^0.16`, `rector-phpunit:^1.0`) conflict with Rector 2.x
  - Fixes installation errors when Rector 2.x is installed

### Documentation

- Updated README.md with Rector 2.x compatibility information
- Updated UPGRADING.md with instructions for Rector 2.x
- Updated CHANGELOG.md with detailed Rector 2.x compatibility notes

## [1.0.4] - 2024-12-19

### Added

- **Test coverage for Rector version detection**: Added comprehensive tests for `getRectorVersion()` method
  - 5 new test cases covering all scenarios (Rector 1.x, 2.x, not installed, invalid version)
  - New test file: `RectorVersionDetectionTest.php`
  - Improved test coverage for Plugin class

### Improved

- **Dockerfile**: Enhanced development container setup
  - Added `curl` to system dependencies
  - Container now fully ready for dependency installation and test execution
  - All necessary tools pre-installed (PHP, Composer, PCOV, Git, etc.)

## [1.0.3] - 2024-12-19

### Fixed

- **PHP 8.1 compatibility**: Removed typed constants (`private const int`) which require PHP 8.3+
  - All constants now use untyped syntax compatible with PHP 8.1+
  - Fixed PHPUnit code coverage HTML generation error
- **PHP-CS-Fixer configuration conflict**: Removed conflicting `single_blank_line_before_namespace` rule
  - This rule conflicts with `blank_lines_before_namespace` included in `@PSR12`
  - Configuration now works correctly without conflicts
- **Rector package version compatibility**: Fixed compatibility issues with Rector 2.x
  - Plugin now detects Rector version and handles optional packages accordingly
  - **For Rector 1.x**: Optional packages (`rector-symfony`, `rector-doctrine`, `rector-phpunit`) are installed normally
  - **For Rector 2.x**: Optional packages are automatically skipped (not compatible yet)
  - These packages (`rector-symfony:^1.0`, `rector-doctrine:^0.16`, `rector-phpunit:^1.0`) conflict with Rector 2.x
  - Fixes installation errors when Rector 2.x is installed

## [1.0.2] - 2024-12-18

### Changed

- **Configuration file naming**: Renamed `.php-cs-fixer.dist.php` to `.php-cs-fixer.php` for consistency with `.rector.php` naming
  - All configuration files now follow the same naming pattern (without `.dist` suffix)
  - Updated all references in documentation, tests, and examples
- **Template formatter installation**: Template formatter configurations are now only installed if their corresponding template engine dependencies are detected
  - Twig-CS-Fixer config is only installed if `twig/twig` package is present
  - Improved detection logic to work across all frameworks (not just Symfony/Generic)
  - Added informative messages when template engines are not detected

### Added

- **Automatic Composer Scripts Installation**: The plugin now automatically adds Composer scripts to your `composer.json` during installation. Scripts are added based on the detected framework and installed dependencies:
  - `cs-check` and `cs-fix` for PHP-CS-Fixer (all frameworks)
  - `rector` and `rector:fix` for Rector (all frameworks)
  - `twig-check` and `twig-fix` for Twig-CS-Fixer (if Twig is installed)
  - `blade-check` and `blade-fix` for Laravel Blade templates
  - `test` for PHPUnit (if installed)
  - Existing scripts are never overwritten
  - Scripts are sorted alphabetically for better readability
- **Template engine documentation**: Comprehensive documentation of template engines used by each framework
  - Added "Template Engines by Framework" section in README
  - Documented all template engines (Twig, Blade, Smarty, PHP native views, etc.)
  - Listed template engines without dedicated formatters yet
  - Added future template formatter support roadmap
- **Execution order documentation**: Added clear documentation about the correct order to run quality tools
  - PHP-CS-Fixer should run first
  - Rector should run second
  - Template formatters should run last
  - Added explanation of why order matters

### Improved

- **Plugin template detection**: Twig-CS-Fixer is now available for all frameworks if Twig is installed
  - Previously only available for Symfony and Generic
  - Now works with Laravel, Yii, CakePHP, Laminas, Slim, and Generic
  - Better framework-agnostic template engine detection
- **Code comments**: Enhanced code comments in Plugin.php with detailed template engine information
  - Documented template engines by framework
  - Added instructions for adding future template formatters
  - Improved code maintainability

## [1.0.1] - 2024-12-XX

### Fixed

- Minor bug fixes and improvements

## [1.0.0] - 2024-12-11

### Compatibility

- **PHP**: >= 8.1 (tested with 8.1, 8.2, 8.3, 8.4, 8.5)
- **Symfony**: 6.0 - 7.4
- **Laravel**: 9.0 - 11.0
- **Composer**: >= 2.0

### Added

- **Automatic dependency installation**: Plugin detects and offers to install suggested dependencies (Rector, PHP-CS-Fixer, Twig-CS-Fixer)
  - Interactive prompts for missing dependencies during installation
  - Framework-specific dependency suggestions
  - Non-interactive mode shows manual installation commands
- **Automatic framework detection** during installation:
  - Symfony: `symfony/framework-bundle` or `symfony/symfony`
  - Laravel: `laravel/framework`
  - Yii: `yiisoft/yii2`
  - CakePHP: `cakephp/cakephp`
  - Laminas: `laminas/laminas-mvc`
  - CodeIgniter: `codeigniter4/framework`
  - Slim: `slim/slim`
  - Generic: Fallback for any PHP project
- **Framework-specific configurations**:
  - **Symfony**: Full Rector with SymfonySetList, Doctrine, PHPUnit, Twig-CS-Fixer
    - Supports Symfony 6.0 - 7.4
    - PHP 8.1 - 8.5
    - Suggested packages: `rector/rector`, `rector/rector-symfony`, `rector/rector-doctrine`, `rector/rector-phpunit`, `friendsofphp/php-cs-fixer`, `vincentlanglet/twig-cs-fixer`
  - **Laravel**: Rector with Laravel sets, PHP-CS-Fixer
    - Supports Laravel 9.0 - 11.0
    - PHP 8.1 - 8.5
    - Suggested packages: `rector/rector`, `driftingly/rector-laravel`, `friendsofphp/php-cs-fixer`
  - **Generic**: Works with any PHP project
    - PHP 8.1 - 8.5
    - Suggested packages: `rector/rector`, `friendsofphp/php-cs-fixer`, `vincentlanglet/twig-cs-fixer`
- Rector configuration with customizable settings
  - PHP 8.1 - 8.5 support
  - Doctrine, PHPUnit integration
  - Configurable feature flags
  - Framework-specific rule sets
- PHP-CS-Fixer configuration
  - PSR-12 + Symfony standards
  - Comprehensive rule set
  - Customizable paths and rules
- Twig-CS-Fixer configuration (Symfony and Generic only)
  - Standard Twig formatting rules
  - Customizable paths and disabled rules
- Composer plugin for automatic file installation
- Files are NEVER overwritten after first install
- Separate `*.custom.php` files for project-specific settings
- PHPUnit tests
- GitHub Actions CI/CD pipeline
- PHP-CS-Fixer configuration (PSR-12)
- Comprehensive documentation
- Docker development environment
- Makefile for common development tasks
- Git pre-commit hooks

### Notes

- Configuration files are only copied on first install
- Custom files (`*.custom.php`) are never overwritten
- Main config files are never overwritten after installation
- Dependencies are suggested but not required (can be installed manually)
- Framework detection is automatic based on `composer.json` dependencies
