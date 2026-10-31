# Usage

## Table of contents

- [Run quality tools](#run-quality-tools)
- [Typical local flow](#typical-local-flow)
- [Docker development (this repository)](#docker-development-this-repository)
- [Gate scripts (since 1.2.0)](#gate-scripts-since-120)
  - [`check-coverage-threshold`](#check-coverage-threshold)
  - [`check-module-boundaries`](#check-module-boundaries)
- [Release guards (since 1.3.0)](#release-guards-since-130)
  - [`assert-latest-tag`](#assert-latest-tag)
  - [Local release gate: `make release-check`](#local-release-gate-make-release-check)
  - [`assert-release-proof`](#assert-release-proof)
- [Parallel PHPUnit isolation (since 1.5.0)](#parallel-phpunit-isolation-since-150)
  - [`pid_path` env var processor](#pid_path-env-var-processor)
  - [`clean-stale-pid-paths`](#clean-stale-pid-paths)
  - [Parallel coverage gate (Makefile recipe)](#parallel-coverage-gate-makefile-recipe)
- [Front-end and docs gates (since 1.6.0)](#front-end-and-docs-gates-since-160)
  - [`check-no-inline-svg`](#check-no-inline-svg)
  - [`check-asset-budgets`](#check-asset-budgets)
  - [`check-doc-links`](#check-doc-links)
  - [`php-worker-reset` (Makefile snippet)](#php-worker-reset-makefile-snippet)
- [Query budgets in PHPUnit (since 1.2.0)](#query-budgets-in-phpunit-since-120)
  - [Wiring (Symfony, test environment only)](#wiring-symfony-test-environment-only)
  - [API](#api)
- [Route query scaling through the profiler (since 1.7.0)](#route-query-scaling-through-the-profiler-since-170)
- [Missing translations of rendered pages (since 1.7.0)](#missing-translations-of-rendered-pages-since-170)
- [AI agent co-author trailers (since 1.7.0)](#ai-agent-co-author-trailers-since-170)
  - [`git-hygiene` (Makefile snippet)](#git-hygiene-makefile-snippet)
- [Quiet runs and focused test runs (since 1.8.0)](#quiet-runs-and-focused-test-runs-since-180)
  - [`quiet-run`](#quiet-run)
  - [`junit-failures`](#junit-failures)
  - [`changed-tests`](#changed-tests)
  - [Makefile recipes](#makefile-recipes)
- [Developer tooling for long gates (since 1.9.0)](#developer-tooling-for-long-gates-since-190)
  - [`heavy-lock`](#heavy-lock)
  - [`memory-guard`](#memory-guard)
  - [`release-phase`](#release-phase)
  - [`check-changed-src-tested`](#check-changed-src-tested)
  - [`coverage-gaps`](#coverage-gaps)
  - [`slowest-tests`](#slowest-tests)
  - [Makefile snippets: `heavy.mk`, `quiet.mk`, `release-phases.mk`](#makefile-snippets-heavymk-quietmk-release-phasesmk)

## Run quality tools

Use Composer scripts from your project (after enabling `extra.php-quality-tools.auto_add_scripts` or adding scripts manually — see [Configuration](CONFIGURATION.md)):

```bash
composer fix:check
composer fix
composer rector:check
composer rector
composer test
```

If Twig tooling is installed:

```bash
composer twig:lint
composer twig:fix
```

## Typical local flow

1. Run style checks and fixes (`fix:check` / `fix`).
2. Run Rector in dry run (`rector:check`).
3. Run tests (`test`).
4. Apply Rector (`rector`) and re-run tests.

## Docker development (this repository)

In this repository, you can use:

```bash
make up
make cs-check
make phpstan
make test
```

## Gate scripts (since 1.2.0)

Two CI gates ship as Composer binaries (`vendor/bin/…`). They are plain PHP (no shell, `python3` or
ripgrep needed) and do nothing unless you call them; the Composer plugin behaviour is unchanged.

### `check-coverage-threshold`

```bash
vendor/bin/check-coverage-threshold <clover.xml> [<min-percent>] [--min=N] [--metric=both|statements|lines]
```

- Reads a Clover report (PHPUnit `--coverage-clover`).
- **Statements** come from `project/metrics` (`coveredstatements` / `statements`); **lines** are the
  distinct `<line type="stmt|cond">` entries per file and line number (covered when `count > 0`).
- The minimum is the second argument, `--min=N`, or the `COVERAGE_MIN` environment variable. Without a
  minimum, coverage is printed and the command exits 0 (informational mode).
- `--metric=both` (default) checks statements **and** lines; `--metric=statements` reproduces a
  statements-only gate. A report without `<line>` entries skips the line check (`--metric=lines` then
  fails with exit 2).
- Percentages are truncated to two decimals (99.996 % prints `99.99%`, so it never shows `100.00%` while failing).

| Exit code | Meaning |
| --- | --- |
| 0 | at or above the minimum, or informational mode |
| 1 | below the minimum (message on stderr), or report file missing |
| 2 | usage error, invalid minimum, unparsable report or missing `project/metrics` |

```text
$ COVERAGE_MIN=100 vendor/bin/check-coverage-threshold var/coverage/clover.xml
Statement coverage: 100.00% (5210/5210) from var/coverage/clover.xml
Line coverage: 100.00% (5210/5210) from var/coverage/clover.xml
Threshold: OK (Statement coverage 100.00% >= 100.00%)
Threshold: OK (Line coverage 100.00% >= 100.00%)
```

Makefile example (Docker Compose):

```makefile
COVERAGE_MIN ?=
coverage-threshold:
	$(DC) exec -T -e COVERAGE_MIN="$(COVERAGE_MIN)" php vendor/bin/check-coverage-threshold var/coverage/clover.xml
```

### `check-module-boundaries`

```bash
vendor/bin/check-module-boundaries [--config=<file>] [--root=<dir>]
```

- Config: `--config`, else `config/quality/module-boundaries.yaml` (then `.yml`, then `.php`) under the root.
  YAML needs `symfony/yaml`; a PHP file returning the same array works without it.
- Root: `--root`, else the current directory. Every path in the config is relative to the root.
- Output: one `error: …` line per violation (offending `file:line` entries indented below it) on stderr,
  or one `OK: module boundaries (…)` line on stdout.

| Exit code | Meaning |
| --- | --- |
| 0 | no violation |
| 1 | at least one violation |
| 2 | usage error, config missing / invalid (unknown keys, unknown modules, bad regex …) |

#### Config reference

```yaml
paths: [src]              # scan roots for edge checks and rules without "in" (default: every module path)
ignore: ['src/Legacy/']   # never scanned by edges / forbid_* rules
references: use           # use (default): top-level `use` imports only; all: also \Fully\Qualified names in code

modules:                  # name => namespace prefix + directory (path optional: namespace-only targets)
  Shared: { namespace: 'App\Shared', path: src/Shared }
  Project: { namespace: 'App\Project', path: src/Project }
  Vendor: 'Psr'           # shorthand: namespace only

allowed_edges:            # allow-list: a listed module may only import these modules (itself always allowed)
  Shared: [Vendor]

forbidden_edges:          # deny-list with reviewed exceptions
  - from: Shared          # string or list
    to: [Project]
    except: [src/Shared/Listing/]
    message: 'only src/Shared/Listing/ may import App\Project.'

rules:                    # extra checks (all optional "message")
  - { type: forbid_path, path: src/Shared/Metrics }                       # must not exist (file or dir)
  - { type: require_path, path: [src/Kernel.php, src/Ops/] }              # must exist
  - { type: forbid_import, in: [src/Identity], imports: ['App\Project\Service\Foo', 'App\Performance\Entity\'] }
  - { type: forbid_pattern, in: [src], except: [src/Ingest/Otlp/], pattern: 'Otlp.*Controller' }
  - { type: require_pattern, path: src/Ingest/Handler.php, pattern: 'IssueWriter' }   # missing files are skipped
  - { type: require_near, path: [src/Project/Entity/Project.php], pattern: 'ORM\\OneToMany\(', requires: 'EXTRA_LAZY', within_lines: 4 }
```

- A file belongs to the module with the **longest** matching `path`; an imported name to the module with
  the longest matching `namespace` (case-insensitive). Imports of the file's own module and of names outside
  every module are ignored.
- `use` detection is token based: grouped imports (`use App\Foo\{A, B}`), `use function` and `use const`
  count; trait `use` inside classes and closure `use (...)` do not.
- Path patterns (`ignore`, `except`, `in`, `path`): exact path, or a directory prefix (`src/Shared/Listing/`
  or `src/Shared/Listing`), or an `fnmatch` glob when the pattern has `*`, `?` or `[` (`*` also matches `/`).
- `imports` entries are exact names, or namespace prefixes when they end with `\`.
- Regexes are PCRE **without delimiters**, multi-line mode (`^` / `$` per line). Keep them single-quoted in
  YAML so backslashes stay literal.
- `require_near`: every line matching `pattern` needs `requires` on that line or within the next
  `within_lines` lines.

A complete real-world map (Symfony Beacon, 7 modules, 3 forbidden edges, 18 rules) lives in
[`tests/Fixtures/module-boundaries/beacon.yaml`](../tests/Fixtures/module-boundaries/beacon.yaml); the test
suite runs it on a fake module tree and checks every rule fails on its regression.

## Release guards (since 1.3.0)

### `assert-latest-tag`

```bash
vendor/bin/assert-latest-tag [<tag>] [--prefix=v] [--stdin] [--fetch[=<remote>]] [--github-output]
```

Succeeds only when `<tag>` is the **highest semver release tag** of the repository, so a tag-triggered
deploy of an older tag (backfilling `v1.51.0` after `v1.60.0`, or several queued tag runs finishing out of
order) never rolls production back.

- `<tag>` defaults to `GITHUB_REF_NAME` (a leading `refs/tags/` is stripped).
- Release tags are `<prefix>MAJOR.MINOR.PATCH` (default prefix `v`; `--prefix=` for bare `1.2.3`,
  `--prefix=api/v` for `api/v1.2.3`). They are compared numerically (`v1.10.0` > `v1.9.0`). Pre-releases
  (`v2.0.0-rc1`) and other tags are never the latest and never outrank a release.
- Tags come from `git tag --list '<prefix>*'` in the current directory (the checkout needs its tags:
  `actions/checkout` with `fetch-depth: 0`, or `--fetch` to run `git fetch --tags --force --quiet origin`
  first). `--stdin` reads the list from stdin instead (one tag per line), e.g. from `git ls-remote`.
- `--github-output` appends `deploy=true|false` and `latest=<tag>` to `$GITHUB_OUTPUT`, prints a
  `::notice` annotation when the deploy is skipped, and exits 0 either way (the job stays green; later
  steps check `deploy`). Without it, the command is a strict check (exit 1 when not the latest).
- Plain PHP without dependencies: it also runs from a bare `git clone` of this package (no
  `composer install`); `bin/autoload.php` falls back to loading `src/` directly.

| Exit code | Meaning |
| --- | --- |
| 0 | `<tag>` is the latest release tag, or the decision was written with `--github-output` |
| 1 | `<tag>` is not the latest release tag (strict mode) |
| 2 | usage error, no tag (argument / `GITHUB_REF_NAME`), git error, or `$GITHUB_OUTPUT` unset / not writable |

```text
$ vendor/bin/assert-latest-tag v1.59.0
pushed=v1.59.0 latest=v1.60.2
ERROR: v1.59.0 is not the latest release (v1.60.2).
```

#### Deploy workflow (GitHub Actions)

A light guard job decides; the deploy job (often on a self-hosted runner without PHP) only runs for the
latest tag. Pin the package tag you trust:

```yaml
# .github/workflows/deploy-on-tag.yml
name: Deploy on tag

on:
  push:
    tags: ["v*"]

concurrency:
  group: deploy-prod-${{ github.repository }}
  cancel-in-progress: false

jobs:
  guard:
    runs-on: ubuntu-latest          # PHP and git preinstalled
    outputs:
      deploy: ${{ steps.latest.outputs.deploy }}
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0            # all tags
      - name: Guard — deploy only the latest release tag
        id: latest
        run: |
          git clone --quiet --depth 1 --branch v1.3.0 https://github.com/nowo-tech/PhpQualityTools.git "$RUNNER_TEMP/pqt"
          php "$RUNNER_TEMP/pqt/bin/assert-latest-tag" --github-output

  deploy:
    needs: guard
    if: needs.guard.outputs.deploy == 'true'
    runs-on: [self-hosted, linux, x64, prod-vps]
    environment: production
    steps:
      - uses: actions/checkout@v4
        with:
          ref: ${{ github.ref }}
      # … deploy steps …
```

Single job, when the runner has PHP and the project's `vendor/` (Composer install done earlier in the job):

```yaml
      - name: Guard — deploy only the latest release tag
        id: latest
        run: vendor/bin/assert-latest-tag --fetch --github-output
      - name: Deploy
        if: steps.latest.outputs.deploy == 'true'
        run: ./scripts/deploy-prod.sh
```

Without a checkout at all, list the remote tags: `git ls-remote --tags --refs "https://github.com/${GITHUB_REPOSITORY}.git" 'v*' | sed 's#.*refs/tags/##' | php "$RUNNER_TEMP/pqt/bin/assert-latest-tag" --stdin --github-output`
(private repositories need a token in the URL).

### Local release gate: `make release-check`

When hosted CI is unavailable (or before every tag anyway), run one Make target that repeats the CI gates
locally and only then tag. Recommended recipe (Docker Compose service `php`, as in Symfony Beacon):

```makefile
COVERAGE_MIN ?=
GITLEAKS_VERSION ?= 8.28.0   # same version as the CI secret-scan job

test-coverage:
	$(DC) exec -T -e XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-text --coverage-clover var/coverage/clover.xml
	$(DC) exec -T -e COVERAGE_MIN="$(COVERAGE_MIN)" php vendor/bin/check-coverage-threshold var/coverage/clover.xml --metric=statements

secrets-scan:
	docker run --rm -v "$(CURDIR):/repo:ro" -w /repo "zricethezav/gitleaks:v$(GITLEAKS_VERSION)" \
		detect --source . --verbose --redact --exit-code 1

# Local release gate: QA + coverage threshold + secrets scan. Run before `git tag`.
release-check:
	$(MAKE) qa
	$(MAKE) test-coverage COVERAGE_MIN=100
	$(MAKE) secrets-scan
	@echo "release-check OK: qa + 100% PHP coverage + secrets scan passed — safe to tag."
```

- `qa` is the project's full static + test chain (CS, Rector dry run, PHPStan, Twig lint, module
  boundaries, tests …); `COVERAGE_MIN` is your policy (Beacon enforces 100).
- Release flow: `make release-check` → commit → `git tag -a vX.Y.Z` → `git push origin main vX.Y.Z`. The
  tag-triggered deploy then runs `assert-latest-tag`, so re-pushing an old tag is harmless.
- Optionally assert locally that the tag you are about to deploy by hand is the newest:
  `git fetch --tags && vendor/bin/assert-latest-tag vX.Y.Z`.

### `assert-release-proof`

```text
vendor/bin/assert-release-proof [<tag>] [--github-output] [--allow-skip]
```

Succeeds only when the **annotated** tag message has the line `release-check: OK <full sha of the tagged commit>` — the proof that the local release gate passed on exactly that commit. Tag it right after a green `make release-check`:

```bash
git tag -a vX.Y.Z -m "Release vX.Y.Z" -m "release-check: OK $(git rev-parse HEAD)"
```

Tag-triggered deploys use it as the gate while hosted CI cannot run (or as a second gate next to a green `ci.yml`). A lightweight tag, or a proof line for another commit, fails (exit 1). `--github-output` writes `proof=true|false` and exits 0; `--allow-skip` accepts `RELEASE_GATE=skip` from the environment and prints a `::warning` (emergency override, logged in the run). Exit 2 on usage or git errors (needs the tags fetched: `actions/checkout` `fetch-depth: 0`).

## Parallel PHPUnit isolation (since 1.5.0)

ParaTest runs the suite in N PHPUnit processes at once. File-based test state configured from one env var
(setup markers, scratch / lock directories, a SQLite file) is then shared by every process and the runs
step on each other (one process deletes the marker another one just wrote). Two helpers give each process
its own paths and clean them up afterwards.

### `pid_path` env var processor

`NowoTech\PhpQualityTools\Testing\Env\PidPathEnvVarProcessor` appends `-<pid>` of the current PHP
process to a path (trailing slashes removed): `/dev/shm/app-markers/` → `/dev/shm/app-markers-12345`.
It does not touch `$_ENV` / `putenv()`; an empty or non-string value throws a `RuntimeException`.

Register it in the **test** environment only (this package is a dev dependency, absent from a
`--no-dev` production install):

```yaml
# config/services.yaml (or config/services_test.yaml without the when@test key)
when@test:
    services:
        NowoTech\PhpQualityTools\Testing\Env\PidPathEnvVarProcessor:
            tags: [container.env_var_processor]
```

Use it wherever a test-only path comes from an env var, with Symfony's `default:` processor for a
fallback parameter when the variable is unset:

```yaml
when@test:
    parameters:
        app.test_marker_dir: '/dev/shm/app-markers'
    some_bundle:
        marker_file: '%env(pid_path:default:app.test_marker_dir:APP_TEST_MARKER_DIR)%/setup.done'
```

The suffix is the PID of the process that **resolves** the variable (env vars are resolved at runtime,
not at compile time), so a container compiled once into `var/cache/test` still gives every ParaTest
worker its own path.

### `clean-stale-pid-paths`

```text
vendor/bin/clean-stale-pid-paths [--dry-run] [--quiet] <pattern> [<pattern> ...]
```

Removes the per-process paths of processes that no longer exist. Each pattern has exactly one `{pid}`
placeholder and optional `*` / `?` wildcards (never across `/`); quote the patterns so the shell does not
expand them. Directories are removed recursively, symlinks are unlinked (never followed), and the paths of
**running** processes are kept, so it is safe next to a test run still in progress (a coverage run next
to `make test`).

```bash
vendor/bin/clean-stale-pid-paths --quiet \
    '/dev/shm/app-phpunit-{pid}.db*' \
    '/dev/shm/app-markers-{pid}'
```

Liveness comes from `/proc/<pid>` when procfs is mounted, else `posix_kill($pid, 0)`; when neither is
available nothing is removed. `--dry-run` lists what would go. Exit codes: 0 OK (also when nothing
matched), 1 a path could not be removed, 2 usage error.

### Parallel coverage gate (Makefile recipe)

ParaTest (`composer require --dev brianium/paratest`) merges the coverage of every process, so the
existing `check-coverage-threshold` gate works unchanged on its Clover report:

```makefile
PARATEST_PROCESSES ?= 12

test-coverage-parallel:
	$(DC) exec -T php vendor/bin/clean-stale-pid-paths --quiet '/dev/shm/app-phpunit-{pid}.db*' '/dev/shm/app-markers-{pid}'
	$(DC) exec -T php sh -c 'mkdir -p var/coverage'
	$(DC) exec -T -e XDEBUG_MODE=coverage php vendor/bin/paratest --processes=$(PARATEST_PROCESSES) \
		--coverage-text --coverage-clover var/coverage/clover.xml
	$(DC) exec -T -e COVERAGE_MIN="100" php vendor/bin/check-coverage-threshold var/coverage/clover.xml --metric=statements
```

- Warm the test cache **once** before the parallel phase (`bin/console cache:warmup --env=test`) so the
  workers do not all compile the container at the same time.
- No pipes in gate recipes (`… | tee` hides a failing exit code); run the gate jobs with
  `make -j<N> --output-sync=target` so each job's output prints as one block.
- Per-process databases follow the same idea (e.g. a SQLite file per PID); add their pattern to the
  cleanup call.

## Front-end and docs gates (since 1.6.0)

Three more CI gates for applications with Twig templates, a Vite build and Markdown docs (harvested from
Symfony Beacon, where they were a PHPUnit test and two Node scripts). Plain PHP, no Node needed; same
conventions as the other gates: `OK …` on stdout, problems on stderr, exit 0 OK, 1 violations, 2 usage error.

### `check-no-inline-svg`

```text
vendor/bin/check-no-inline-svg [--root=<dir>] [--templates=<dir>]... [--scripts=<dir>]...
                               [--template-ext=<.ext,...>] [--script-ext=<.ext,...>]
                               [--allow=<path>]... [--no-scripts]
```

One icon source (e.g. Symfony UX Icons from an imported local set): no hand-written SVG.

- **Templates** (`--templates`, default `templates/`; suffixes `--template-ext`, default `.twig`): fail on `<svg`.
- **Scripts** (`--scripts`, default `assets/`; suffixes `--script-ext`, default `.ts`): fail on SVG markup
  strings (`<svg`, `<path`, `<circle`, `<rect`, `<polyline`, `<polygon`). Render the icon server-side in a
  `<template>` and clone it instead. `--no-scripts` checks templates only.
- Skipped everywhere: `node_modules/`, `*.test.*`, `*.spec.*`, `*.d.ts`. Default directories that do not
  exist are skipped; an explicit `--templates` / `--scripts` directory that does not exist is a usage error.
- `--allow=<path>` (relative to the root, repeatable) keeps a file that genuinely needs inline SVG, such as
  a third-party `<symbol>` sprite. An allowed file that is missing or no longer contains SVG is an error, so
  the allowlist cannot go stale.

```text
$ vendor/bin/check-no-inline-svg --allow=templates/bundles/NelmioApiDocBundle/SwaggerUi/index.html.twig
OK: no inline SVG (245 template(s), 45 script(s), 1 allowed).

$ vendor/bin/check-no-inline-svg
error: inline <svg> in templates/components/badge.html.twig:12 (render the icon through the icon component, e.g. ux_icon())
error: SVG markup string in assets/controllers/chart_controller.ts:40 (render the icon server-side in a <template> and clone it)
```

### `check-asset-budgets`

```text
vendor/bin/check-asset-budgets [--root=<dir>] [--config=<file>] [--manifest=<file>] [--build-dir=<dir>]
                               [--budget=<entry>:<kind>=<size>]...
```

Reads the Vite manifest of a **production build** (`build.manifest: true`; run `pnpm run build` /
`npm run build` first) and, for every budgeted entry, sums the entry file plus its **statically imported**
chunks (JS) and their CSS. Dynamic imports (lazy controllers) are not counted: they load only where used.

- Manifest: `--manifest`, the config's `manifest`, else `public/build/.vite/manifest.json` (Vite 5+), then
  `public/build/manifest.json`. Build directory: `--build-dir`, the config's `build_dir`, else the manifest
  directory without `.vite/`.
- Budgets: `config/quality/asset-budgets.{yaml,yml,json,php}` (or `--config`) and/or `--budget` (repeatable;
  overrides the config for the same entry and kind).
- Kinds: `js`, `css` (raw bytes) and `js_gzip`, `css_gzip` (gzip level 9, needs ext-zlib). Sizes: bytes, or
  a number with `B`, `k` / `kB` (1000), `KiB` (1024), `M` / `MB`, `MiB` (case-insensitive).
- Output: one `OK  ` / `FAIL` line per check with raw and gzip sizes, then a summary.

| Exit code | Meaning |
| --- | --- |
| 0 | every entry within budget |
| 1 | over budget, entry missing from the manifest (renamed?), manifest or built file missing (build first) |
| 2 | usage error, no budget, invalid config / sizes / kinds, malformed manifest |

```yaml
# config/quality/asset-budgets.yaml (needs symfony/yaml; .json or a .php file returning the array work too)
manifest: public/build/.vite/manifest.json   # optional
build_dir: public/build                       # optional
budgets:
  assets/app.ts: { js: 200kB, css: 250kB }
  assets/styles/shell-entry.scss: { css: 60kB }
  assets/kit-admin.ts: { js: 30kB }
  assets/styles/kit-admin.css: { css: 60kB }
  assets/styles/dashboard-summary-entry.scss: { css: 20kB, css_gzip: 5kB }
```

```text
$ vendor/bin/check-asset-budgets
OK   assets/app.ts js: 148.9 kB (gzip 43.4 kB) / budget 200.0 kB
OK   assets/app.ts css: 248.5 kB (gzip 36.3 kB) / budget 250.0 kB
OK   assets/styles/shell-entry.scss css: 43.4 kB (gzip 6.6 kB) / budget 60.0 kB
OK   assets/kit-admin.ts js: 5.1 kB (gzip 1.7 kB) / budget 30.0 kB
OK   assets/styles/kit-admin.css css: 35.3 kB (gzip 5.9 kB) / budget 60.0 kB
OK   assets/styles/dashboard-summary-entry.scss css: 7.0 kB (gzip 1.8 kB) / budget 20.0 kB
OK   assets/styles/dashboard-summary-entry.scss css_gzip: gzip 1.8 kB (raw 7.0 kB) / budget 5.0 kB
OK: asset budgets (7 check(s), 5 entries).
```

A build rewrites `public/build/`, so long-lived PHP workers must be restarted afterwards (see
[`php-worker-reset`](#php-worker-reset-makefile-snippet)):

```makefile
asset-budgets: ensure-up
	$(DC) exec -T php sh -c 'pnpm run build >/dev/null && vendor/bin/check-asset-budgets'
	@$(MAKE) php-worker-reset
```

### `check-doc-links`

```text
vendor/bin/check-doc-links [--root=<dir>] [--exclude=<pattern>]... [--check-anchors] [--no-git]
```

- Files: the tracked and new, not ignored `*.md` files (`git ls-files --cached --others --exclude-standard`),
  so `vendor/`, `var/` and other ignored trees are never read. Outside a git work tree (or with `--no-git`)
  every `*.md` under the root, except `.git/`, `vendor/`, `node_modules/` and `var/`.
- Every `[text](target)` / `![alt](target)` with a relative target must point to an existing file or
  directory (query strings and `#fragments` are ignored for that check; `%20` is decoded). `/target` is
  relative to the root (GitHub semantics). Nested brackets work (`[![badge](img.svg)](docs/x.md)` checks
  both targets).
- Skipped: URLs and other schemes (`https:`, `mailto:` …), fenced code blocks, inline code spans, targets
  that climb above the root (workspace-only links).
- `--exclude=<pattern>` (repeatable): a path, a directory prefix or an `fnmatch` glob relative to the root.
- `--check-anchors` (opt-in): `#fragment`s into Markdown files and in-page `#fragment`s must match a heading
  slug (GitHub rules: lower case, punctuation removed, spaces to `-`, `-1`, `-2` … for repeated headings;
  ATX and setext headings, not inside fenced code), an HTML anchor (`<a id="…">`, `<a name="…">`, any tag's
  `id="…"`) or a heading attribute (`## Title {#custom-id}`, Pandoc / kramdown / MkDocs). Matching is
  case-insensitive. Fragments into other files (`src/Foo.php#L10`) are not checked.

```text
$ vendor/bin/check-doc-links
OK: doc links (481 Markdown files, no broken relative link or image).

$ vendor/bin/check-doc-links --check-anchors
Broken relative links / images / anchors (2):
docs/manual/01-getting-started.md:55 → 03-account.md#profile (no such anchor)
docs/guide.md:3 → images/missing.webp
```

### `php-worker-reset` (Makefile snippet)

Long-lived PHP workers (FrankenPHP worker mode, RoadRunner, Swoole) keep the Vite entrypoints / manifest
they read at boot. After a front-end build they must be restarted, or they serve stale asset URLs. The
package ships a Docker Compose snippet, `resources/make/php-worker-reset.mk`, with every name in a variable:

| Variable | Default | Meaning |
| --- | --- | --- |
| `PHP_WORKER_RESET_COMPOSE` | `docker compose` | compose command of the main (dev) stack |
| `PHP_WORKER_RESET_EXTRA_COMPOSE` | empty | optional second stack (e.g. E2E); reset only when its PHP service is running |
| `PHP_WORKER_RESET_SERVICE` | `php` | PHP service name in both stacks |
| `PHP_WORKER_RESET_EXTRA_SERVICES` | empty | (since 1.6.1) space-separated services restarted after the PHP service in both stacks, without `cache:clear` (e.g. `messenger messenger-notify`); waited for like the PHP service (no healthcheck = continue at once). A failed restart fails the target in the main stack and is ignored in the extra stack (service not defined there) |
| `PHP_WORKER_RESET_CACHE_CLEAR` | `bin/console cache:clear --no-warmup` | command run in the service before the restart (failure ignored) |
| `PHP_WORKER_RESET_TIMEOUT` | `90` | seconds to wait per container for `healthy` (no healthcheck = healthy at once) |

For each stack: `cache:clear`, `restart <service>` (the container env is kept), then wait until every
container of the service is healthy; exit 1 when one is not healthy in time. With
`PHP_WORKER_RESET_EXTRA_SERVICES`, those services are restarted right after the PHP service (no
`cache:clear` in them) and waited for too: workers that share `var/cache` with PHP (Messenger consumers)
would otherwise rebuild the cache from the old container they hold in memory, with unresolved
`%parameters%`.

```makefile
DC := docker compose -f compose.yaml
DC_E2E := docker compose -p myapp-e2e --env-file .env.e2e -f compose.yaml -f compose.e2e.yaml

PHP_WORKER_RESET_COMPOSE := $(DC)
PHP_WORKER_RESET_EXTRA_COMPOSE := $(DC_E2E)
PHP_WORKER_RESET_EXTRA_SERVICES := messenger messenger-notify   # optional
-include vendor/nowo-tech/php-quality-tools/resources/make/php-worker-reset.mk
php-worker-reset: ensure-up   # optional extra prerequisite of the host Makefile

vite-build: ensure-up
	$(DC) exec -T php pnpm run build
	@$(MAKE) php-worker-reset
```

`-include` keeps the Makefile usable before `composer install` (the target is then missing). The `vendor/`
directory must be visible on the host (the usual bind mount); otherwise copy the file into the project.

## Query budgets in PHPUnit (since 1.2.0)

`NowoTech\PhpQualityTools\Testing\QueryBudgetTrait` counts the SQL statements executed around a callable.
The statements are recorded by a Doctrine **DBAL 4** middleware into a `QueryCounter`. Install the dev
dependencies the host does not already have: `composer require --dev doctrine/dbal:^4 phpunit/phpunit`.

### Wiring (Symfony, test environment only)

```yaml
# config/services_test.yaml
services:
    NowoTech\PhpQualityTools\Testing\QueryCounter:
        public: true
    NowoTech\PhpQualityTools\Testing\Dbal\QueryCountingMiddleware:
        arguments: ['@NowoTech\PhpQualityTools\Testing\QueryCounter']
        tags: [doctrine.middleware]
```

Counted: `Connection::query()`, `Connection::exec()` and every execution of a prepared statement. Pass
`true` as the second middleware argument to count `START TRANSACTION` / `COMMIT` / `ROLLBACK` as well (the
Symfony profiler's Doctrine collector counts them too). Without Symfony, add the middleware with
`Doctrine\DBAL\Configuration::setMiddlewares([new QueryCountingMiddleware($counter)])`.

### API

```php
use NowoTech\PhpQualityTools\Testing\QueryBudgetTrait;
use NowoTech\PhpQualityTools\Testing\QueryCounter;

final class IssueListBudgetTest extends WebTestCase
{
    use QueryBudgetTrait;

    protected function getQueryCounter(): QueryCounter // required by the trait
    {
        return static::getContainer()->get(QueryCounter::class);
    }

    public function testIssueListBudget(): void
    {
        $client = static::createClient();
        // Fails above 60 statements; the failure lists the executed SQL. Returns the count.
        $this->assertQueryBudget(60, fn () => $client->request('GET', '/issues'));
    }

    public function testIssueListDoesNotScaleWithRows(): void
    {
        $client = static::createClient();
        $this->assertScalesWith(
            fn (int $n, int $previous) => $this->seedIssues($previous, $n), // bring the data set to N rows
            fn () => $client->request('GET', '/issues'),
            sizes: [5, 50],   // strictly increasing, at least two
            tolerance: 0,     // max(count) - min(count) allowed
            warmUp: true,     // run the operation once unmeasured after the first preparation
        );
    }
}
```

| Method | Returns | Fails when |
| --- | --- | --- |
| `recordQueries(callable $operation)` | `list<string>` SQL run by the operation | — |
| `countQueries(callable $operation)` | `int` | — |
| `assertQueryBudget(int $max, callable $operation, string $message = '')` | `int` count | more than `$max` statements |
| `assertScalesWith(callable $prepare, callable $operation, array $sizes = [5, 50], int $tolerance = 0, bool $warmUp = true, string $message = '')` | `array<int, int>` count by N | largest count > smallest + `$tolerance` |

`assertScalesWith()` calls `$prepare(N, previousN)` (previousN is 0 the first time) before measuring each N,
so the callback can add only the missing rows `[previousN, N)`. Invalid sizes or a negative tolerance throw
`InvalidArgumentException`.

The trait reads `getQueryCounter()` again after the operation: when the kernel was rebooted in between
(`KernelBrowser` reboots before each request unless `disableReboot()`), the new counter holds exactly the
operation's statements.

## Route query scaling through the profiler (since 1.7.0)

`NowoTech\PhpQualityTools\Testing\RouteQueryScalingTrait` measures whole requests of a `KernelBrowser` with the
profiler's `db` collector (DoctrineBundle's `DoctrineDataCollector`, or any collector named `db` with
`getQueryCount()`). Needs `symfony/browser-kit` and the profiler in the test environment:

```yaml
# config/packages/test/framework.yaml (or when@test)
framework:
    profiler: { collect: false }   # the trait enables collection for its own requests
```

```php
use Doctrine\ORM\EntityManagerInterface;
use NowoTech\PhpQualityTools\Testing\RouteQueryScalingTrait;

final class IssueListScalingTest extends WebTestCase
{
    use RouteQueryScalingTrait;

    public function testIssueListDoesNotScaleWithIssues(): void
    {
        $client = static::createClient();
        $this->assertRouteQueriesIndependentOfRows(
            $client,
            '/issues',
            static function (EntityManagerInterface $em, int $from, int $to): void { // persist rows [$from, $to)
                for ($i = $from; $i < $to; ++$i) {
                    $em->persist(new Issue('Issue '.$i));
                }
            },
            small: 5,            // rows of the first measurement
            large: 50,           // rows of the second measurement
            rowMarker: 'Issue 49', // must render in the second response (no empty-list false pass)
            tolerance: 0,        // |large - small| allowed
        );
    }

    public function testDashboardBudget(): void
    {
        $this->assertRouteQueryBudget(static::createClient(), '/dashboard', 60);
    }
}
```

| Method | Returns | Fails when |
| --- | --- | --- |
| `measureRouteQueries(KernelBrowser $client, string $path, string $method = 'GET')` | `int` queries of that request | non-2xx response, no profile, no `db` collector |
| `assertRouteQueryBudget(KernelBrowser $client, string $path, int $max, string $message = '')` | `int` count | more than `$max` queries |
| `assertRouteQueriesIndependentOfRows(KernelBrowser $client, string $path, Closure $seed, int $small = 5, int $large = 50, ?string $rowMarker = null, int $tolerance = 0)` | `array{int, int}` counts with `$small` / `$large` rows | the counts differ by more than `$tolerance` |

The flow is: seed `[0, small)`, one unmeasured warm-up request (first-visit side effects), measure, seed
`[small, large)`, measure. `$seed($objectManager, $from, $to)` gets `routeScalingObjectManager()` — the
`doctrine.orm.entity_manager` service, or `null` without Doctrine ORM; override it for another manager — which is
flushed and cleared after each call. Invalid sizes or a negative tolerance throw `InvalidArgumentException`.

The trait only needs the test case's `static::getContainer()` (Symfony's `KernelTestCase`). It is independent of
`QueryBudgetTrait` (DBAL middleware, statement lists): use that one when you need the SQL in the failure message.

## Missing translations of rendered pages (since 1.7.0)

`NowoTech\PhpQualityTools\Testing\MissingTranslationsTrait` reads Profiler → Translation → "Missing messages" of one
request. Needs `symfony/browser-kit`, `symfony/translation`, `framework.translator` and the profiler in the test
environment.

```php
use NowoTech\PhpQualityTools\Testing\MissingTranslationsTrait;

final class PagesAreTranslatedTest extends WebTestCase
{
    use MissingTranslationsTrait;

    public function testAdminPagesUseNoMissingMessage(): void
    {
        $client = static::createClient();
        foreach (['/es/admin/users', '/es/admin/settings'] as $url) {
            $this->assertNoMissingTranslations($client, $url, allowlist: ['validators: *']);
            // The page stays available: $client->getCrawler()
        }
    }
}
```

| Method | Returns | Fails when |
| --- | --- | --- |
| `collectMissingTranslations(KernelBrowser $client, string $url, string $method = 'GET')` | `list<array{locale, domain, id}>`, unique, in first-use order | non-2xx response, no profile, no `translation` collector |
| `assertNoMissingTranslations(KernelBrowser $client, string $url, array $allowlist = [], string $method = 'GET')` | `list<string>` allowlisted messages that were ignored | any other missing message (all listed as `locale domain: id`) |

An allowlist entry matches `"locale domain: id"`, `"domain: id"` or `"id"`; a trailing `*` matches a prefix
(`"validators: *"`, `"es messages: legacy.*"`).

## AI agent co-author trailers (since 1.7.0)

Three POSIX sh scripts (git + awk, no PHP: they run on the host even when PHP lives in a container) keep AI agent
attribution out of commit messages: `Co-authored-by`, `Made-with`, `Generated-by`, `Generated-with` and
`Assisted-by` trailers (case-insensitive) naming an agent. Human co-authors are untouched.

| Agent | Matches (in the trailer value) |
| --- | --- |
| `cursor` | `cursor` (`@cursor.com`, `Made-with: Cursor`) |
| `claude` | `claude`, `anthropic.com` |
| `copilot` | `copilot` |
| `codex` | `codex`, `openai.com` |
| `gemini` | `gemini` |
| `aider` (opt-in) | `aider` |
| `devin` (opt-in) | `devin-ai`, `devin.ai` |

Default agents: `cursor,claude,copilot,codex,gemini` (`--agents` or `$AGENT_COAUTHOR_AGENTS`); `--pattern` /
`$AGENT_COAUTHOR_PATTERN` adds an extended regex (lower-case).

```bash
vendor/bin/check-no-agent-coauthor                         # every commit reachable from HEAD
vendor/bin/check-no-agent-coauthor origin/main..HEAD       # a range (e.g. the commits of a PR)
vendor/bin/check-no-agent-coauthor --since-file .scripts/agent-coauthor-baseline   # accepted older history
vendor/bin/check-no-agent-coauthor --message-file .git/COMMIT_EDITMSG
vendor/bin/install-git-hooks [--hooks-dir .githooks] [--agents cursor,claude] [--prepare-commit-msg] [--force] [--no-config]
```

- `check-no-agent-coauthor` exits 0 when clean, 1 listing the offending commits and lines, 2 on a usage or git
  error. `--since <sha>` / `--since-file <file>` audit only `<sha>..<ref>` when `<sha>` is an ancestor of `<ref>`;
  after a history rewrite (the baseline is gone) everything is audited again. An explicit range ignores the baseline.
- `install-git-hooks` copies `strip-agent-coauthor` as `commit-msg` (standalone: works without `vendor/` in the
  checkout), bakes the agent list in and sets `core.hooksPath` to the hooks directory. A hook it did not install is
  only replaced with `--force`.

### `git-hygiene` (Makefile snippet)

```make
GIT_HYGIENE_SINCE_FILE := .scripts/agent-coauthor-baseline   # optional
-include vendor/nowo-tech/php-quality-tools/resources/make/git-hygiene.mk

release-check: check-no-agent-coauthor
```

| Variable | Default | Meaning |
| --- | --- | --- |
| `GIT_HYGIENE_REF` | `HEAD` | Ref or range checked by `check-no-agent-coauthor` |
| `GIT_HYGIENE_AGENTS` | (script default) | Comma-separated agents (check and installed hook) |
| `GIT_HYGIENE_SINCE_FILE` | — | Baseline file (`--since-file`) |
| `GIT_HYGIENE_HOOKS_DIR` | `.githooks` | Hooks directory of `install-git-hooks` |
| `GIT_HYGIENE_HOOK_FLAGS` | — | Extra `install-git-hooks` flags (`--force`, `--prepare-commit-msg`, `--no-config`) |
| `GIT_HYGIENE_BIN` | `vendor/nowo-tech/php-quality-tools/bin` | Where the scripts live |

## Quiet runs and focused test runs (since 1.8.0)

Three helpers that keep long gates readable (for humans and AI agents) and give a fast pre-check before the full
suite. `quiet-run` and `changed-tests` are POSIX sh (they run on the host even when PHP lives in a container);
`junit-failures` is PHP 8.2+ and needs no dependency.

```bash
vendor/bin/quiet-run var/quiet/release-check.log -- make release-check
vendor/bin/junit-failures var/coverage/junit.xml          # only the failed / errored tests
vendor/bin/phpunit $(vendor/bin/changed-tests)            # tests covering what changed since the last tag
```

### `quiet-run`

```text
quiet-run [options] <log> -- <command> [<args>...]
```

Runs `<command>` with stdout and stderr written to `<log>` (its directory is created; ANSI colour / cursor codes and
carriage-return progress redraws are stripped, so the log keeps the last state of every progress line) and prints a
short summary instead of the whole output:

- **Success:** `OK <label> (<seconds>s) — full log: <log>` and the key result lines matched by `--summary`
  (PHPUnit `OK (…)` / `Tests: …`, coverage, PHPStan `No errors` / `Found N errors`, audits, `release-check OK`).
- **Failure:** `FAIL <label> (<seconds>s, exit <code>) — full log: <log>`, then the make targets that failed
  (`make: *** […]` lines, de-duplicated), the failed PHPUnit tests of every JUnit XML file written during the run
  (through `junit-failures`), and the last `--context` lines before the first error line (blank lines and PHPUnit /
  ParaTest progress lines skipped; the end of the log when no error line is found).

| Option | Environment | Default | Meaning |
| --- | --- | --- | --- |
| `--label NAME` | `QUIET_LABEL` | `<log>` without directory and `.log` | Label of the summary line |
| `--context N` | `QUIET_CONTEXT` | `15` | Lines shown before the first error (`0` hides them) |
| `--summary ERE` | `QUIET_SUMMARY` | PHPUnit, coverage, PHPStan, audit and `release-check OK` lines | Result lines kept on success |
| `--summary-lines N` | `QUIET_SUMMARY_LINES` | `15` | Result lines shown at most on success (`0` hides them) |
| `--error ERE` | `QUIET_ERROR` | `make: ***`, `FAILURES!`, `ERRORS!`, `[ERROR]`, `ERROR` / `FAIL` / `Fatal error` at line start | First error line (start of the context) |
| `--junit-dir DIR` (repeatable) | `QUIET_JUNIT_DIRS` (space separated) | `var build` plus the log's directory | Directories searched for `*junit*.xml` files newer than the start of the run (`vendor/`, `node_modules/` skipped) |
| `--junit-cmd CMD` | `QUIET_JUNIT_CMD` | `php <package>/bin/junit-failures` (skipped without `php`) | Command printing the failures of one JUnit file (the path is appended) |

Exit codes: the exit code of `<command>` (so `make` still fails on a failed gate), `2` on a usage error.

When PHP only runs in a container, point `--junit-cmd` at it:
`QUIET_JUNIT_CMD='docker compose exec -T php vendor/bin/junit-failures'`. Paths are passed relative to the current
directory, so the container must mount the project at its working directory.

### `junit-failures`

```text
junit-failures [--max=<n>] [--lines=<n>] [--strip-prefix=<path>]... <junit.xml> [<max>] [<lines>]
```

Prints only the failed and errored test cases of a JUnit log (PHPUnit / ParaTest `--log-junit`, nested suites
included), one block per test: `Class::method (file:line)` and the first `<lines>` non-empty lines of the message.

```text
Failed tests in var/coverage/junit.xml (2):
  App\Tests\Billing\InvoiceTest::testTotal (tests/Billing/InvoiceTest.php:42)
      App\Tests\Billing\InvoiceTest::testTotal
      Failed asserting that 90 is identical to 100.
  … and 1 more (see the JUnit log).
```

- `<max>` / `--max` (default `15`): tests printed at most; the rest are counted.
- `<lines>` / `--lines` (default `6`): message lines per test.
- `--strip-prefix` (repeatable; default the current directory, e.g. `/app` inside a container): removed from the
  test file paths.

Exit codes: `0` no failed test (nothing printed), `1` failed tests listed, `2` usage error or missing / unreadable
log. In a Makefile recipe that already failed, append `|| true`.

### `changed-tests`

```text
changed-tests [--base REF] [--src DIR]... [--tests DIR]... [--suffix SUFFIX]
```

Prints (sorted, one per line, relative to the repository root, existing files only) the test files that cover what
changed since `<base>`:

- test files changed since `<base>` (committed, staged and working-tree changes) and untracked test files;
- test files that name, as a whole word, the class (file name without `.php`) of a source file changed since
  `<base>` or untracked.

Deleted files are ignored. It is a fast pre-check, not a gate: run the whole suite (with coverage) before a release.

| Option | Environment | Default | Meaning |
| --- | --- | --- | --- |
| `--base REF` | `BASE` | last tag reachable from `HEAD` (`git describe --tags --abbrev=0`) | Base of the diff |
| `--src DIR` (repeatable) | `CHANGED_TESTS_SRC` (space separated) | `src` | Source directories |
| `--tests DIR` (repeatable) | `CHANGED_TESTS_DIRS` (space separated) | `tests` | Test directories |
| `--suffix SUFFIX` | `CHANGED_TESTS_SUFFIX` | `Test.php` | Test file suffix |

Exit codes: `0` (also when the list is empty), `2` on a usage or git error (outside a repository, no base and no tag,
unknown ref).

### Makefile recipes

```make
QUALITY_BIN := vendor/bin

# make quiet T=<target> [VAR=value …]: any target with a short summary; the full log in var/quiet/<target>.log
quiet:
	@[ -n "$(T)" ] || { echo "usage: make quiet T=<target>"; exit 2; }
	@$(QUALITY_BIN)/quiet-run var/quiet/$(T).log -- $(MAKE) --no-print-directory $(T)

# PHPUnit on the tests covering the changes since BASE (default: the last tag); prints only the failures
test-changed:
	@tests=$$($(QUALITY_BIN)/changed-tests); \
	if [ -z "$$tests" ]; then echo "test-changed: no test covers the changes"; exit 0; fi; \
	mkdir -p var/quiet; \
	vendor/bin/phpunit --no-coverage --no-progress --log-junit var/quiet/test-changed.junit.xml $$tests > var/quiet/test-changed.log 2>&1; rc=$$?; \
	tail -n 3 var/quiet/test-changed.log; \
	$(QUALITY_BIN)/junit-failures var/quiet/test-changed.junit.xml || true; \
	exit $$rc
```

With PHP in Docker Compose, run PHPUnit and `junit-failures` through `docker compose exec -T php …` and export
`QUIET_JUNIT_CMD='docker compose exec -T php vendor/bin/junit-failures'` for `quiet-run`.

## Developer tooling for long gates (since 1.9.0)

Helpers for long suites and multi-phase release gates on a **shared developer machine** (several projects, agents
or humans running Docker on one host, e.g. Docker Desktop on WSL2): one heavy job at a time, a memory guard, one log
per gate phase, and the reports that say where a failed coverage gate needs work. The shell helpers are POSIX sh and
run on the host; `coverage-gaps` and `slowest-tests` are PHP 8.2+ without dependencies.

```bash
vendor/bin/heavy-lock run "my-app: release-check" make release-check   # wait the FIFO turn and load < 6, always release
vendor/bin/memory-guard -- vendor/bin/paratest --processes=2                # stops the suite below 4 GB MemAvailable
vendor/bin/release-phase php-coverage make test-coverage                  # log + OK / FAIL line in var/quiet/release/
vendor/bin/check-changed-src-tested                                       # changed classes no test names (hint)
vendor/bin/coverage-gaps var/coverage/clover.xml                          # files below 100 % and their lines
vendor/bin/slowest-tests var/coverage/junit.xml 10                        # slowest test files of the last run
```

### `heavy-lock`

```text
heavy-lock acquire <owner> | release | status | run <owner> <cmd> [<args>...]
```

A machine-wide lock for heavy jobs (coverage suites, e2e, release checks, image builds):

- **FIFO:** every waiter writes a ticket (`<epoch-ns>-<pid>`) in the queue directory; only the oldest live ticket may
  take the lock, so a job that releases and re-acquires at once queues behind everyone already waiting. Tickets of dead
  waiters are pruned. `DOCKER_HEAVY_PRIORITY=1` sorts a ticket before every normal one (it never interrupts the holder).
- **Load threshold:** the lock is taken only while the 1-minute load average is below `DOCKER_HEAVY_MAXLOAD` (default 6).
- **Stale holders:** `run` records its own PID; a bare `acquire` records `DOCKER_HEAVY_LOCK_PID` (or `-`). The first
  waiter removes a holder whose PID is gone, or a `-` holder older than `DOCKER_HEAVY_LOCK_MAXAGE` seconds (default
  3600), and logs it to `DOCKER_HEAVY_STALE_LOG`.
- **Reentrancy:** `run` sets `DOCKER_HEAVY_LOCK_HELD=1` for the command; nested `acquire` / `release` / `run` are no-ops.

| Environment | Default | Meaning |
| --- | --- | --- |
| `DOCKER_HEAVY_LOCK_DIR` | `/tmp/docker-heavy.lock` | Lock directory (`owner`: label, UTC time, PID, epoch) |
| `DOCKER_HEAVY_QUEUE_DIR` | `/tmp/docker-heavy.queue` | Queue of waiting tickets |
| `DOCKER_HEAVY_STALE_LOG` | `/tmp/docker-heavy.stale.log` | Removed stale locks |
| `DOCKER_HEAVY_MAXLOAD` | `MAXLOAD` or `6` | Start only below this load average |
| `DOCKER_HEAVY_POLL` | `POLL` or `10` | Seconds between checks while waiting |
| `DOCKER_HEAVY_LOCK_MAXAGE` | `3600` | Age after which a holder without PID is stale |

The directory and variable names are those of the nowo-tech workspace helper `docker-heavy-lock.sh`, so both share one
lock on the same machine. With the lock in `/tmp`, a reboot clears stale state. Keep each heavy job at **two processes
at most** (`paratest --processes=2 --max-batch-size=5`, Playwright `--workers=2`; `HEAVY_PROCESSES` in `heavy.mk`).
Exit codes: `0`; `run` exits with the command's code; `1` acquire failed; `2` usage error.

### `memory-guard`

```text
memory-guard [--kill '<command>'] [--] <suite command> [<args>...]
```

Runs the suite and every `MEMGUARD_INTERVAL` seconds (default 20) logs the load average, `MemAvailable` and the three
largest PHP / ParaTest / Playwright / Chromium / Node processes to stderr. When `MemAvailable` drops below
`MEMGUARD_MIN_MB` (default 4096) it runs the `--kill` command (e.g. `docker compose exec -T php pkill -f paratest`, to
stop the workers inside the project's own container; never a host-wide `pkill` on a shared machine, it would stop other
projects' suites too) and stops the suite, which then fails cleanly instead of taking the container
runtime down. `MEMGUARD=0`, or a host without `/proc/meminfo` (`MEMGUARD_MEMINFO`), runs the suite unguarded.
Exit codes: the suite's (non-zero when it was stopped), `2` on a usage error.

### `release-phase`

```text
release-phase <phase> <command> [<args>...]  |  --reset  |  --summary [<title>]
```

Runs one phase of a multi-phase gate: the output is streamed and kept in `$RELEASE_PHASE_DIR/<phase>.log` (default
`var/quiet/release`), and `OK   <phase> <s>s` or `FAIL <phase> <s>s — log: <path>` is appended to `summary.txt`. On a
failure it prints the summary so far (title `RELEASE_PHASE_TITLE`, default `release-check`), so the failing phase is
named at the end of the output. `--reset` starts a new summary; `--summary` prints it.

```text
release-check phases:
  OK   check-fast 94s
  OK   quick 141s
  FAIL php-coverage 1012s — log: var/quiet/release/php-coverage.log
```

Exit codes: the command's, `2` on a usage error (no command, phase name other than letters, digits, `.`, `-`, `_`).

### `check-changed-src-tested`

```text
check-changed-src-tested [--base REF] [--src DIR]... [--tests DIR]... [--strict]
```

An early hint before the long coverage run: lists the PHP files under the source directories **added, modified or renamed
in the commits** between `<base>` and `HEAD` (default base: `COVERAGE_HINT_BASE`, `BASE`, then the last tag) whose class
(file name without `.php`) no PHP file under the test directories names as a whole word. Not a gate by default
(functional tests cover controllers by route, not by name); `--strict` exits `1` when classes are listed. Outside a
repository or without a base it prints nothing. `--src` / `--tests` and `CHANGED_TESTS_SRC` / `CHANGED_TESTS_DIRS` are
shared with `changed-tests`. Exit `2` on a usage error or an unknown base ref.

### `coverage-gaps`

```text
coverage-gaps [--strip-prefix=<path>]... <report>
```

Lists the files below 100 % of a **Clover XML** (PHPUnit; statements with `count="0"`) or **LCOV** (Vitest, Jest, c8;
`DA:<line>,0`) report and their uncovered lines as ranges; the format is detected from the content. Paths lose
`--strip-prefix` (default: the current directory, e.g. `/app` in a container).

```text
coverage-gaps: 2 file(s) below 100 % (uncovered lines):
  src/Billing/Invoice.php                                                          12, 20-22
  assets/app.ts                                                                    2-3, 5
```

Exit codes: `0` fully covered, `1` gaps listed, `2` usage error or missing / unreadable report. Typical use after a
failed threshold: `vendor/bin/check-coverage-threshold var/coverage/clover.xml 100 || { vendor/bin/coverage-gaps var/coverage/clover.xml; exit 1; }`.

### `slowest-tests`

```text
slowest-tests [--limit=<n>] [--strip-prefix=<path>]... [<junit.xml>] [<limit>]
```

Sums the test case times of a JUnit log (PHPUnit / ParaTest `--log-junit`; default `var/coverage/junit.xml`) per test
file (the class when there is no `file` attribute) and prints the slowest `<limit>` files (default 20) with their share
of the total. Exit codes: `0`, `2` usage error or missing / unreadable log; append `|| true` after a suite.

### Makefile snippets: `heavy.mk`, `quiet.mk`, `release-phases.mk`

Every name is a variable (`?=`); `QUALITY_TOOLS_BIN` defaults to `vendor/nowo-tech/php-quality-tools/bin` and
`QUALITY_PHP_EXEC` (empty: on the host) prefixes the PHP commands, e.g. `docker compose exec -T php`.

```make
QUALITY_PHP_EXEC := docker compose exec -T php
QUIET_JUNIT_CMD := $(QUALITY_PHP_EXEC) vendor/bin/junit-failures
QUIET_JUNIT_DIRS := var/coverage var/quiet
RELEASE_PHASES := check-fast tested-src=check-changed-src-tested quick=release-quick php-coverage=test-coverage
RELEASE_PHASE_FLAGS_check-fast := -j2 --output-sync=target   # make flags of one phase
RELEASE_PHASE_FLAGS_quick := -k -j2 --output-sync=target
-include vendor/nowo-tech/php-quality-tools/resources/make/heavy.mk
-include vendor/nowo-tech/php-quality-tools/resources/make/quiet.mk
-include vendor/nowo-tech/php-quality-tools/resources/make/release-phases.mk

release-check: release-phases

test-coverage:
	$(MEMORY_GUARD) --kill '$(QUALITY_PHP_EXEC) pkill -f paratest' -- \
		$(QUALITY_PHP_EXEC) vendor/bin/paratest --processes=$(HEAVY_PROCESSES) --max-batch-size=5 \
		--coverage-clover var/coverage/clover.xml --log-junit var/coverage/junit.xml
	$(QUALITY_PHP_EXEC) vendor/bin/check-coverage-threshold var/coverage/clover.xml 100 || { $(MAKE) coverage-gaps; exit 1; }
	@$(MAKE) test-slowest N=5 || true
```

| Snippet | Target | What it does | Variables |
| --- | --- | --- | --- |
| `heavy.mk` | `heavy-<target>` | `$(MAKE) <target>` through `heavy-lock run "<label>: <target>[ <T>]"`; nested `heavy-*` calls go straight through. Never dry-run it with `make -n` (make still runs the recursive line). | `HEAVY_LOCK` (empty: no lock), `HEAVY_SKIP` (default `$(CI)`: no lock on CI), `HEAVY_LOCK_LABEL` (directory name) |
| `heavy.mk` | `heavy-status` | Holder, queue and load | — |
| `heavy.mk` | — | `$(MEMORY_GUARD)` and `$(HEAVY_PROCESSES)` (2) for recipes | `MEMGUARD_*` (environment) |
| `quiet.mk` | `quiet T=<target>` | Any target through `quiet-run` (full log in `$(QUIET_LOG_DIR)/<T>.log`); `make heavy-quiet T=release-check` adds the lock | `QUIET_LOG_DIR` (`var/quiet`), `QUIET_JUNIT_CMD`, `QUIET_JUNIT_DIRS` |
| `quiet.mk` | `test-changed` | PHPUnit (no coverage) on `changed-tests`, then only the failures (`junit-failures`) | `TEST_CHANGED_PHPUNIT` (`--log-junit <file>` and the tests are appended), `BASE` |
| `release-phases.mk` | `release-phases` | Every `RELEASE_PHASES` entry (`<name>=<target>` or `<target>`) through `release-phase`, in order, stopping at the first failure; then the summary | `RELEASE_PHASE_FLAGS_<name>`, `RELEASE_PHASE_DIR`, `RELEASE_PHASES_TITLE`; `$(call release_phase,<name>,<command>)` in custom recipes |
| `release-phases.mk` | `check-changed-src-tested` | The hint above | `COVERAGE_HINT_STRICT=1` fails on a hit |
| `release-phases.mk` | `coverage-gaps`, `test-slowest` | The reports above | `COVERAGE_GAPS_REPORT` (`var/coverage/clover.xml`), `SLOWEST_TESTS_JUNIT` (`var/coverage/junit.xml`), `N` (30) |

A host that already defines one of these targets keeps its own by not including that snippet; the existing bins and
the 1.8 recipes above are unchanged.
