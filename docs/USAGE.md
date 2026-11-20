# Usage

## Table of contents

- [Run quality tools](#run-quality-tools)
- [Typical local flow](#typical-local-flow)
- [Docker development (this repository)](#docker-development-this-repository)
- [Gate scripts (since 1.2.0)](#gate-scripts-since-120)
  - [`check-coverage-threshold`](#check-coverage-threshold)
  - [`check-module-boundaries`](#check-module-boundaries)
    - [Layer rules per context (since 1.12.0)](#layer-rules-per-context-since-1120)
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
  - [`coverage-resume` (since 1.13.0)](#coverage-resume-since-1130)
  - [Makefile snippet: `coverage-resume.mk` (since 1.13.0)](#makefile-snippet-coverage-resumemk-since-1130)
  - [Makefile snippets: `heavy.mk`, `quiet.mk`, `release-phases.mk`](#makefile-snippets-heavymk-quietmk-release-phasesmk)
- [Shared test helpers (since 1.14.0)](#shared-test-helpers-since-1140)
  - [Adopting without config files](#adopting-without-config-files)
  - [`ConfigurationThreadStackTestCase`](#configurationthreadstacktestcase)
  - [`WorkerMemoryAssertions`](#workermemoryassertions)
  - [`RecordingLogger`](#recordinglogger)
  - [`SqliteManagerRegistry`](#sqlitemanagerregistry)
  - [`TwigPathsPassTestCase`](#twigpathspasstestcase)
  - [Two-connection race tests: `Testing\Concurrency\`](#two-connection-race-tests-testingconcurrency)
- [Bundle repository checks (since 1.14.0)](#bundle-repository-checks-since-1140)
  - [`check-demo-lock`](#check-demo-lock)
  - [`check-no-raw-html-form`](#check-no-raw-html-form)
  - [`check-twig-extra`](#check-twig-extra)
  - [`check-translation-key-parity`](#check-translation-key-parity)
- [Test hygiene gates (since 1.11.0)](#test-hygiene-gates-since-1110)
  - [`check-coverage-annotations`](#check-coverage-annotations)
  - [`check-wallclock-fixtures`](#check-wallclock-fixtures)
  - [Enabling them](#enabling-them)

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
# since 1.12.0, maps with a "layers" section only:
vendor/bin/check-module-boundaries [--baseline=<file>] [--strict-baseline]
vendor/bin/check-module-boundaries --update-baseline [--allow-grow] [--baseline=<file>]
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

require_all_declared: true  # since 1.11.0, default false: every directory directly under src/ must be a module path
                            # (or lie below one, or match ignore); a list of roots works too: [src, lib]
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
- `require_all_declared` (since 1.11.0, off by default): a new top-level `src/*` directory that nobody added to
  `modules` fails at once (`Undeclared module directories …`), instead of slipping past every edge rule. A module whose
  path is a subdirectory (`src/Catalog/Sub`) does not declare its parent `src/Catalog`; a module with path `src` declares
  everything; `ignore` patterns exempt a directory. A missing root is reported.

A complete real-world map (Symfony Beacon, 7 modules, 3 forbidden edges, 18 rules) lives in
[`tests/Fixtures/module-boundaries/beacon.yaml`](../tests/Fixtures/module-boundaries/beacon.yaml); the test
suite runs it on a fake module tree and checks every rule fails on its regression.

#### Layer rules per context (since 1.12.0)

An optional `layers` section declares layers inside some modules (here called **contexts**) and checks rules between
them, for an incremental move to Domain-Driven Design: each migrated context is switched to `enforce`, the others are
reported (`warn`), and today's findings sit in a baseline that may only shrink. A map without `layers` behaves exactly
as before (same output, same exit codes; the new flags are refused with exit 2).

Beacon-like example (also [`tests/Fixtures/module-boundaries/beacon-layers.yaml`](../tests/Fixtures/module-boundaries/beacon-layers.yaml),
run by the test suite):

```yaml
modules:
  Uptime: { namespace: 'App\Uptime', path: src/Uptime }
  Billing: { namespace: 'App\Billing', path: src/Billing }
  Identity: { namespace: 'App\Identity', path: src/Identity }
  Shared: { namespace: 'App\Shared', path: src/Shared }

layers:
  # Layer => sub-directory of the module path; the namespace is derived (UI -> App\<Module>\UI).
  # Default: Domain, Application, Infrastructure, UI (src/<Module>/<Layer>). Forms:
  #   ~ (the layer name), 'Dir', [DirA, DirB/Sub], { path: [Dir], namespace: [Ns] }
  definitions:
    Domain: ~
    Application: ~
    Infrastructure: ~
    UI: { path: UI, namespace: UI }
  domain_allow: ['Doctrine\Common\Collections\']   # allow-list of the @default Domain rule (default shown)
  contexts:                          # only these modules are checked; the others keep today's behaviour
    Uptime: enforce                  # migrated: a new finding fails the run (exit 1)
    Billing:                         # not migrated yet: findings are printed as warnings (exit 0)
      mode: warn
      layers:                        # per-context override: today's directories count as their future layer
        Domain: [Domain, Entity]
        Application: [Application, Service, MessageHandler]
        Infrastructure: [Infrastructure, Repository]
        UI: [UI, Controller, Form, Command]
    Identity: { mode: warn, layers: { UI: [UI, Controller] } }
  rules:                             # default: ['@default']
    - '@default'                     # the four default rules below
    - id: application-no-ui          # id: [A-Za-z0-9_.-], the baseline key
      type: forbid_layers
      in: [Domain, Application]      # source layers (or not_in: [...], which also selects files outside every layer)
      to: [UI]
      message: 'use cases do not depend on controllers, forms or Twig.'
    - id: ui-no-other-context-infrastructure
      type: forbid_layers
      in: [UI]
      to: [Infrastructure]
      scope: other                   # any (default) | same | other context
    - id: no-container
      type: forbid_names
      not_in: [Infrastructure]
      contexts: [Uptime]             # optional: only these contexts
      names: ['Symfony\Component\DependencyInjection\ContainerInterface', 'Psr\Container\*']
  baseline: config/quality/module-boundaries-baseline.txt   # default shown
```

The `@default` rules:

| Rule id | Type | Files | Fails on |
| --- | --- | --- | --- |
| `domain-layer-edges` | `forbid_layers` | `in: [Domain]` | a name of the `UI` or `Infrastructure` layer of **any** context |
| `domain-framework-free` | `forbid_names` | `in: [Domain]` | `Symfony\…`, `Doctrine\…`, except `domain_allow` (default `Doctrine\Common\Collections\`) |
| `ui-no-persistence` | `forbid_names` | `in: [UI]` | `Doctrine\ORM\EntityManagerInterface`, `Doctrine\ORM\EntityManager`, `Doctrine\Persistence\ObjectManager`, `Doctrine\Persistence\ManagerRegistry`, `*Repository`, `*RepositoryInterface` |
| `flush-in-application` | `forbid_calls` | `not_in: [Application, Infrastructure]` | `->flush(` / `?->flush(` |

Rule types (every rule: `id`, `type`, exactly one of `in` / `not_in`, optional `contexts`, optional `message`):

| Type | Keys | Fails when a file of the selected layers … |
| --- | --- | --- |
| `forbid_layers` | `to`, `scope` | references a name in one of the `to` layers |
| `allow_layers` | `to`, `scope` | references a name in a layer other than its own and the `to` layers (names outside every layer are not checked) |
| `forbid_names` | `names`, `allow` | references a name matching `names` and not `allow` (exact name, namespace prefix ending in `\`, or `*` / `?` glob over the whole name; case-insensitive) |
| `forbid_calls` | `methods` | calls `->method(` / `?->method(` (case-insensitive) |

How it works:

- A file belongs to the context with the longest matching module `path` (a nested module is its own context), and to
  the layer with the longest matching `<module path>/<layer path>`; a file outside every layer has no layer (selected
  only by `not_in` rules). The global `ignore` applies.
- A referenced name belongs to the module with the longest matching namespace and to the layer with the longest
  matching `<module namespace>\<layer namespace>`, for **every** module: a module that is not a context yet uses the
  global `definitions` (so "Domain must not use the UI of any context" also covers contexts migrated later).
- Names come from the tokens, never from comments or strings: every top-level `use` (grouped `use A\{B, C as D}`,
  aliases, `use function` / `use const`), every fully-qualified name (`\App\Foo`, `\App\Foo::class`, attributes,
  type hints) and every qualified name, resolved: through an imported alias when its first segment is one
  (`use Doctrine\ORM;` + `ORM\EntityManagerInterface` is `Doctrine\ORM\EntityManagerInterface`; `#[ORM\Column]` with
  `use Doctrine\ORM\Mapping as ORM;` is `Doctrine\ORM\Mapping\Column`), else against the namespace
  (`Infrastructure\Foo::class` inside `namespace App\Uptime;` is `App\Uptime\Infrastructure\Foo`). An unqualified
  name (`Foo::class`) is covered by its `use` line or is the file's own namespace. Each name is reported once per file
  and rule (first line); each call is reported. Files PHP cannot compile (duplicate modifiers, syntax newer than the
  running PHP) are still tokenized and checked.
- Limits: dynamic calls (`$r->$method()`, `->{'flush'}()`, `call_user_func([$r, 'flush'])`), class names in strings and
  service ids are not seen; a file whose `namespace` does not match its directory references its declared namespace
  with unqualified names, which are not recorded (its layer is taken from its path).
- `layers` does not change `references`, `allowed_edges`, `forbidden_edges`, `rules` or `require_all_declared`.

Output and exit codes with `layers`:

- `enforce` context, finding not in the baseline: `error: <file>:<line> references <name> (<Context> <Layer>, layer rule <id>): <message>` on stderr, exit 1.
- `warn` context, finding not in the baseline: the same line as `warning: …` on stdout, exit 0.
- Finding in the baseline: counted only (`… N baselined …`).
- Baseline entry that no longer occurs: `warning: baseline entry no longer occurs (remove it, or run --update-baseline): <entry>`;
  with `--strict-baseline` it is an `error:` and the run fails.
- A summary line on stdout: `Layers: 3 context(s), 10 file(s), 7 rule(s): 0 error(s), 4 warning(s), 15 baselined, 0 stale baseline entries.`

Baseline (`layers.baseline`, or `--baseline=<file>` relative to the working directory; a missing file is empty):

```text
# check-module-boundaries layer baseline (php-quality-tools >= 1.12.0): accepted findings, one
# rule|file|subject line each. It may only shrink: regenerate with --update-baseline after fixing findings.
domain-framework-free|src/Billing/Entity/Invoice.php|Doctrine\ORM\Mapping
flush-in-application|src/Billing/Controller/InvoiceController.php|->flush()
```

- One line per finding, without line numbers (edits above a finding do not invalidate it); a key repeated N times
  accepts N findings (two `->flush()` calls in a file are two lines). Findings of `warn` and `enforce` contexts both
  go in, so switching a context to `enforce` changes nothing until its lines are removed.
- `--update-baseline` rewrites the file and exits without running the other checks (exit 0). A missing file is
  created with every current finding. On an existing file it keeps only the listed findings that still occur
  (accepted ∩ current): fixed ones are dropped, and new findings, of `enforce` and `warn` contexts alike, are printed
  as `not added (fix it, or pass --allow-grow): …` and left out, so the next run still reports them. With
  `--allow-grow` they are added. The write is atomic (temporary file in the same directory, then rename), only inside
  `--root`, never through a symbolic link, and keeps the file mode.
- The ratchet is a review-time control: a line added by hand pre-accepts a future finding, which only
  `--strict-baseline` rejects while the code does not exist yet, and a deleted baseline is recreated in full. Run
  `--strict-baseline` in CI and protect the baseline file in review (for example with CODEOWNERS).

Adoption: add the `layers` section with every context in `warn`, run `--update-baseline` once and commit the baseline,
then add `--strict-baseline` to CI so fixed findings must leave the file. Migrate a context, switch it to `enforce`,
and run `--update-baseline` to drop its lines.

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
vendor/bin/assert-release-proof [<tag>] [--github-output] [--allow-skip] [--require-coverage=full]
```

Succeeds only when the **annotated** tag message has the line `release-check: OK <full sha of the tagged commit>` — the proof that the local release gate passed on exactly that commit. Tag it right after a green `make release-check`:

```bash
git tag -a vX.Y.Z -m "Release vX.Y.Z" -m "release-check: OK $(git rev-parse HEAD)"
```

When the tag message also has a line `release-check coverage: full` or `release-check coverage: resumed (k test files, m source files)` (since 1.13.0, from [`coverage-resume`](#coverage-resume-since-1130)), the result shows it. Keep it on its own line: the proof line must stay exactly `release-check: OK <sha>`. `--require-coverage=full` (since 1.13.0) also requires exactly one `release-check coverage: full` line: a resumed coverage run is a pre-check, not a proof, so deploy guards should use it.

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
                               [--budget=<entry>:<kind>=<size>]... [--warn-at=<percent>]
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
- `--warn-at=<percent>` (since 1.11.0; or `warn_at:` in the config, the option wins): a check at or above that share of
  its budget prints `WARN … (94.3% >= 90%)` and still passes; above 100 % it fails as before. The summary adds
  `N warning(s) at >= 90%`. Without a threshold (the default) the output is unchanged. Accepts `90`, `90%` or `87.5`
  (above 0, at most 100).

| Exit code | Meaning |
| --- | --- |
| 0 | every entry within budget |
| 1 | over budget, entry missing from the manifest (renamed?), manifest or built file missing (build first) |
| 2 | usage error, no budget, invalid config / sizes / kinds, malformed manifest |

```yaml
# config/quality/asset-budgets.yaml (needs symfony/yaml; .json or a .php file returning the array work too)
manifest: public/build/.vite/manifest.json   # optional
build_dir: public/build                       # optional
warn_at: 90                                   # optional (since 1.11.0): WARN at >= 90 % of a budget
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
vendor/bin/heavy-lock run "my-app: release-check" make release-check   # wait the turn, the group and the gates; always release
vendor/bin/memory-guard -- vendor/bin/paratest --processes=2                # stops the suite below 4 GB MemAvailable
vendor/bin/release-phase php-coverage make test-coverage                  # log + OK / FAIL line in var/quiet/release/
vendor/bin/check-changed-src-tested                                       # changed classes no test names (hint)
vendor/bin/coverage-gaps var/coverage/clover.xml                          # files below 100 % and their lines
vendor/bin/slowest-tests var/coverage/junit.xml 10                        # slowest test files of the last run
vendor/bin/coverage-resume plan                                           # since 1.13.0: tests a small change can affect
```

### `heavy-lock`

```text
heavy-lock run <owner> <cmd> [<args>...] | acquire <owner> | release [<pid>] | status
```

A machine-wide lock for heavy jobs (coverage suites, e2e, release checks, image builds). `run` and `acquire` need
`flock(1)` (util-linux on Linux, busybox `flock` on Alpine, `brew install flock` on macOS); without it they exit 1 with
that message.

- **Kernel locks (since 1.10.0):** mutual exclusion is `flock`. A job holds, for its whole life, an open descriptor
  locked on its group's lock file (`<DOCKER_HEAVY_GROUP_DIR>/<name>-<cksum>.lock`) and one on a slot's lock file
  (`<DOCKER_HEAVY_GROUP_DIR>/slot.<n>.lock`), the group first. The kernel drops both when the job's process ends, however
  it ends (exit, Ctrl-C, SIGKILL, OOM), so a killed job never leaves a lock behind and there is nothing to judge stale.
  The command run by `run` does not inherit those descriptors, so a daemon it leaves behind cannot keep the slot.
- **Slots:** up to `DOCKER_HEAVY_SLOTS` jobs (default 4) run at once; the setting limits which slot locks this copy
  takes, while every slot in use counts for everyone.
- **Groups:** at most one job per group holds a slot; different groups share the slots. Jobs of one repository share
  its test database, coverage files and stacks, so they must never overlap. The group is `DOCKER_HEAVY_GROUP`; else the
  git repository of the current directory (the main repository's directory name: its worktrees agree, a separate clone
  has its own `.git` and so its own group), unless that name is listed in `DOCKER_HEAVY_IGNORE_REPOS`; else the label
  prefix before `:` (or its first word).
- **Resource gates:** a slot is handed out only while the 1-minute load average is below `DOCKER_HEAVY_MAXLOAD` (default
  10) and `MemAvailable` is at least `DOCKER_HEAVY_MIN_MEM_MB` (default 6144). An unknown load or `MemAvailable` (no
  `/proc/meminfo`, e.g. macOS) keeps the gate closed; set `DOCKER_HEAVY_MIN_MEM_MB=0` there (the first wait says so on
  stderr). The 1-minute load lags, so several slots may be handed out before it reflects them.
- **Queue (fairness only):** every waiter writes a ticket (`<epoch-ns>-<pid>`: label, group) in the queue directory;
  the oldest live ticket goes first, so a job that releases and re-acquires at once queues behind everyone already
  waiting. A later ticket may pass tickets whose group already holds a slot, and may take slots 2..N past a ticket
  without a group line (an older single-slot copy). Tickets of dead waiters are pruned. `DOCKER_HEAVY_PRIORITY=1` sorts
  a ticket before every normal one (it never interrupts a holder). The locks never depend on the queue.
- **`run`** waits, runs the command holding the locks and releases them when the command ends; its exit code is the
  command's. **`acquire`** (bare, for scripts that cannot wrap a command) starts a background holder that keeps the
  locks until `release`, until the process named by `DOCKER_HEAVY_LOCK_PID` exits, or for `DOCKER_HEAVY_LOCK_MAXAGE`
  seconds (default 3600) without either. The holder keeps the slot only once `acquire` has printed `acquired by …` and
  acknowledged it; if `acquire` dies or is interrupted before that, the holder gives the slot up at once. The holder
  keeps none of the caller's descriptors (on dash, bash closes those above 9 when it is installed). Prefer `run`.
- **`release [<pid>]`** stops the bare `acquire` holder watching `<pid>` (default: the caller's parent process), else,
  without an argument, the oldest bare `acquire` without a watched PID. An explicit PID that holds nothing frees
  nothing. A `run` job is released by ending; `release` only says so.
- **Older copies:** php-quality-tools 1.9 and the previous workspace script lock by `mkdir` of `DOCKER_HEAVY_LOCK_DIR`
  (slot 1) and `DOCKER_HEAVY_LOCK_DIR.N`, with an `owner` file (label, UTC time, PID, epoch, group). This version honours
  them both ways: such a directory with a live holder (or, without a PID, younger than `DOCKER_HEAVY_LOCK_MAXAGE`) is an
  occupied slot of that holder's group, and a holder of slot N also creates that directory, with its owner file plus a
  sixth line (`run` or `bare:<watched pid>`), and removes it when it ends. That owner file is what `status` shows. An
  older copy decides by check-then-act on those directories, so two jobs of one group can still overlap while an older
  copy is waiting on the machine; that risk ends once every copy is 1.10.
- **Hardening:** labels and groups lose their control characters (CR, LF, ESC, ...) before they are written; PID and
  epoch lines are validated and never reach shell or awk arithmetic (a malformed older holder is judged by its age only;
  a PID of another user counts as alive). Only the current user's legacy directories are removed, and only when they
  hold nothing but their `owner` file; other files of the queue directory are ignored. A queue or lock-file directory
  that is a symlink, belongs to another user or is writable by other users is refused (new ones are created `0700`),
  a live holder keeps its slot even if a `/tmp` cleaner unlinked the slot's lock file, lock files that are symlinks or
  another user's are never
  opened, and the stale log is never written through a symlink. A non-numeric `DOCKER_HEAVY_SLOTS`,
  `DOCKER_HEAVY_MAXLOAD`, `DOCKER_HEAVY_MIN_MEM_MB`, `DOCKER_HEAVY_POLL` (must be above 0) or `DOCKER_HEAVY_LOCK_MAXAGE`
  falls back to its default with a warning.
- **Status:** each held slot with its group, label, start time and holder PID (`stale` for an older copy's dead
  holder), the queue with each ticket's group, and the load and memory gates.
- **Reentrancy:** `run` sets `DOCKER_HEAVY_LOCK_HELD=1` for the command; nested `acquire` / `release` / `run` are no-ops.
- **Fairness limits:** there is no acquire timeout; the head ticket waits for the gates, and a 1.9.x copy (slot 1 only,
  load below 6) may wait long beside 1.10 copies that keep the other slots busy.

| Environment | Default | Meaning |
| --- | --- | --- |
| `DOCKER_HEAVY_LOCK_DIR` | `/tmp/docker-heavy.lock` | Legacy slot 1 directory, slot N is `<dir>.N` (`owner`: label, UTC time, PID, epoch, group[, kind]) |
| `DOCKER_HEAVY_QUEUE_DIR` | `/tmp/docker-heavy.queue` | Queue of waiting tickets (label, group) |
| `DOCKER_HEAVY_GROUP_DIR` | lock dir with `.lock` → `.group` (`/tmp/docker-heavy.group`) | Kernel lock files of the groups and slots |
| `DOCKER_HEAVY_STALE_LOG` | `/tmp/docker-heavy.stale.log` | Removed stale legacy directories (never a symlink) |
| `DOCKER_HEAVY_SLOTS` | `4` | Slots this copy may take; every slot in use counts |
| `DOCKER_HEAVY_MAXLOAD` | `MAXLOAD` or `10` | Hand out a slot only below this load average |
| `DOCKER_HEAVY_MIN_MEM_MB` | `6144` | Hand out a slot only with this much `MemAvailable` (MB; `0` disables; unknown: closed) |
| `DOCKER_HEAVY_GROUP` | git repository, else label prefix | Group of the job; one job per group holds a slot |
| `DOCKER_HEAVY_IGNORE_REPOS` | — | Space-separated repository names never used as a group (e.g. a workspace that runs no stack) |
| `DOCKER_HEAVY_POLL` | `POLL` or `10` | Seconds between checks while waiting |
| `DOCKER_HEAVY_LOCK_MAXAGE` | `3600` | How long a bare `acquire` without a watched PID is held; age after which an older copy's holder without PID is stale |
| `DOCKER_HEAVY_LOCK_PID` | — | PID a bare `acquire` watches |

The nowo-tech workspace helper `docker-heavy-lock.sh` is generated from this script (it also never groups by the
repository that holds it), so both share one set of locks and rules on the same machine. The `/tmp` defaults are shared
by every account on the machine; on a multi-user host point the variables at a directory of your own (e.g.
`$XDG_RUNTIME_DIR`). With the lock in `/tmp`, a reboot clears all state. Keep each heavy job at **two processes
at most** (`paratest --processes=2 --max-batch-size=5`, Playwright `--workers=2`; `HEAVY_PROCESSES` in `heavy.mk`).
Exit codes: `0`; `run` exits with the command's code; `1` acquire failed (or no `flock`); `2` usage error.

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

### `coverage-resume` (since 1.13.0)

```text
coverage-resume plan | merge | save | status | forget [<options>]
```

After a coverage run (failed or green), the next run reruns only the tests that a **small change** can affect and
merges them into the kept run, instead of the whole suite. It extends the tests-only "resume" of the release gate
(Symfony Beacon D-20261112-04) to small fixes in `src/`. It works from **PHPUnit's per-test coverage**: a
`--coverage-php` file records, for every executable line, the tests that executed it (Clover does not).

> **A resumed green run is a fast pre-check, not a proof.** It is green when every test it reran passed and the merged
> coverage passes the gate; the tests it did not rerun keep their old result. The cases where that differs from a
> full run are listed under [Residual risk](#residual-risk). Run the full suite before a release (CI, nightly, or
> `FORCE_COVERAGE=1`) and make the deploy guard require it: `assert-release-proof --require-coverage=full`.

```bash
vendor/bin/coverage-resume plan > var/rerun.txt   # exit 0: resume, prints the test files; exit 3: full suite (reasons on stderr)
# resume: run the listed test files with --coverage-php var/part.cov --log-junit var/part.xml, then
vendor/bin/coverage-resume merge --coverage=var/part.cov --junit=var/part.xml --clover=var/coverage/clover.xml
# full:   run the suite with --coverage-php var/run.cov --log-junit var/junit.xml --coverage-clover var/coverage/clover.xml
# then the gate (check-coverage-threshold) on the Clover report, and keep the run for the next resume:
vendor/bin/coverage-resume save --coverage=var/run.cov --junit=var/junit.xml \
    --tests-status=passed --status=passed      # (resume: --coverage=<state>/merged.cov)
vendor/bin/coverage-resume status                 # coverage: full | coverage: resumed (k test files, m source files)
```

`coverage-resume.mk` (below) wires the whole cycle; use the commands directly only in a custom recipe. Run them in the
same PHP environment as the tests (same container), from the project root. `--tests-status` is PHPUnit's exit status
(a run can fail with no failing test case: `failOn*` issues, runner warnings, provider errors); `--status` is the
overall result (tests and coverage gate).

**What is kept** (`--state`, default `var/coverage/resume/`): the run's `coverage.cov`, and `state.json`, which holds:

- the content hash of every project file when the run started;
- per source file: its skeleton hash, its function bodies (line ranges, hashes, whether a test executed a line of
  each), and the declared and parent class names;
- which test files executed each source file;
- the failed test files and the tests' status, and the skipped or incomplete test cases;
- the PHP, PHPUnit and php-code-coverage versions, and the coverage driver;
- the run mode and stamp, the resume counters, and the sha1 of `coverage.cov`.

Both files are written through a temporary file and a rename. A state whose `coverage.cov` hash does not match is
ignored (full suite). `pending.json` is the last plan.

**Never restore the state directory from a shared or cross-branch CI cache** (fork pull requests included):
`coverage.cov` is PHP code (`include` + `unserialize`, PHPUnit's own format) and `state.json` decides what is rerun.
Counters that are negative or above 1000 are refused.

**Selection.** A resume reruns, by test file:

- every test that executed any line of a changed source file;
- the tests of every class that extends, implements or uses (trait) a class declared in a changed file, transitively;
- every test file that names a changed class (`--no-name-references` turns this off);
- when a source file changed, the test files the per-test data cannot attribute:
  - files with no recorded line at all;
  - files with coverage metadata (`CoversClass`, `CoversMethod`, `CoversFunction`, `CoversTrait`, `CoversNothing`
    attributes, grouped or not; their import, also aliased in a group `use` (`use …\Attributes\{Small, CoversClass as
    Target}`); `@covers`, `@coversDefaultClass`). php-code-coverage keeps only
    the covered targets of such a test, so the code it runs elsewhere is not recorded against it;
  - files with `setUpBeforeClass()` / `BeforeClass`;
  - files that start a subprocess (`shell_exec`, `exec`, `system`, `passthru`, `proc_open`, `popen`, `new Process`,
    `Process::fromShellCommandline()` and the other `Process::from…()` factories, `PhpProcess`, shell backticks);
  - detection is textual, in the test file itself: the same call in a trait, a base class or a helper elsewhere is
    not seen (see [Residual risk](#residual-risk));
- changed and new test files, the kept run's failed test files, and the `--always` (gate) test files.

**Merge.** From the kept data it drops every line of the changed source files (their line numbers belong to the old
content) and **every contribution of the rerun and deleted test classes, on every file**; then it adds the new run's
data. A line that only a rerun test reached before, and no longer reaches, is uncovered after the merge, as in a full
run. Dropping only the changed files would not be enough: if `Other::thrice()` stops calling `Calc::triple()`,
`Calc.php` is unchanged but `triple()` must become uncovered. php-code-coverage itself writes the merged data, as
`--coverage-php` and Clover, so the report has the same shape and counts as a full run's.

The merge refuses (exit 3, the recipe then runs the full suite) when a test case that ran in the kept run, and was not
skipped there, comes back **skipped** in the rerun. It catches a test that depends on another test file that was not
rerun, when the textual rule below misses the dependency: PHPUnit skips it ("depends on … which has not run"), and a
skipped test does not fail the run. A test skipped for another reason (a `markTestSkipped()` that now applies) also
asks for the full suite.

**Full suite instead** when any of these holds (`plan` prints the reasons):

| Rule | Why |
| --- | --- |
| `--force-full`, `FORCE_COVERAGE=1`, `COVERAGE_RESUME=0` | nightly / pre-release CI, or by hand |
| no kept run, another state format or project root, a `coverage.cov` that is not the state's | nothing to resume from |
| kept run made with another PHP, PHPUnit or php-code-coverage version; the merge also refuses another driver (exit 3) or branch / path data | per-test data and executable lines differ between them |
| the kept run's tests failed and its JUnit log names no failing test case | PHPUnit failed it for `failOn*` issues, runner warnings, a data provider error…: rerunning failing files cannot find it (even with `--trust-junit-failures`) |
| the kept run's tests failed at all, unless `--trust-junit-failures` | the JUnit log does not record data provider errors, runner warnings or `failOn*` issues, whatever the configuration: next to a real failing test case, such a failure may sit in a file that is not rerun. `--trust-junit-failures` reruns only the failing files: use it only when the failing test cases are the only possible cause. A run that failed only the coverage gate (tests passed) is not concerned |
| a resumed run already passed since the last full run (`--max-green-resumes`, default 1) | a resume never stands in for the last full green run for more than one release |
| `--max-resumes` resumes chained since the last full run (default 5) | bounds drift while fixing a failed run |
| a changed file outside the source and test directories (configuration, migrations, `composer.json` / `.lock`, the installed packages, `.env*`, PHPUnit configuration, templates, translations…) | may affect any test |
| a non-test file under the test directories changed, added or deleted (bootstrap, helpers, fixtures) | shared by any test |
| a source file added, deleted or renamed; not PHP; or matching `--full-glob` (default: `*/DependencyInjection/*`, `*Kernel.php`, `*Bundle.php`, `*/Migrations/*`, `*/DataFixtures/*`, `*/Fixtures/*`, `*/Resources/config/*`) | new code has no per-test data; container wiring runs once per kernel boot, not per test |
| a source file changed **outside function bodies**: signature, constant, property default, attribute, doc comment, import, `extends` / `implements`, trait use, a function made a generator | code that runs without executing the file's lines (constants, defaults, reflection on attributes, autoloading, inheritance) |
| a changed function that no test of the kept run executed (code run only by the bootstrap, an extension, a warm-up or a subprocess; an empty body; a body ignored by an annotation) | nobody is recorded as calling it |
| more than `--max-src-files` source files changed (default 10) | a big change is cheaper to verify with the full suite |
| any test file uses another test file's result: `#[DependsExternal…]`, `#[DependsOnClass…]`, `#[Depends…('Other::test')]`, `@depends Other::test` (test-only resumes included) | rerun without its producer, PHPUnit skips the consumer, and a skipped test does not fail the run; a changed producer also changes what it receives. Projects that use them always run the full suite |
| nothing to rerun | — |

Changes are detected by **content**, not by commit: uncommitted, amended and rebased changes all count. The release
flow that puts a fix *below* the release commit still resumes, although the old commit is then no longer an ancestor
of `HEAD`.

`--files-from` limits the comparison to a file list, e.g. `git ls-files -co --exclude-standard`, so ignored build
output does not count. Without it, every file under the root is hashed except `--exclude`. The default excludes are
`.git/`, `vendor/` (but not `vendor/composer/installed.json`), `var/`, `node_modules/`, the PHPUnit and CS caches,
and `coverage/`.

Write the resumed run's own files (a generated PHPUnit configuration…) under an excluded or ignored path, or exclude
them: a file that changes during the run means nothing is kept.

**The stamp** is written only by `save`, with the run it describes. `status` prints it only while that run passed and
the project files are still those it ran on (same content hashes). Otherwise it prints `coverage: unknown (…)` and
exits 1. A plan, an interrupted run or a later edit never turns it into `coverage: full`.

#### Residual risk

A test that is not rerun keeps its result and coverage from the kept run. That is wrong when the test depends on the
change but never executes a line of the changed file in its own per-test data. Each case below was reproduced in
audit QF of PR #74, and none of them is detected:

- **Static state and test order.** Test B reads a static property, a cache, a database row or a file that test A
  fills by running the changed code. B never executes the changed file, so only A is rerun and B keeps its old
  result.
- **Data providers that go through another class.** A provider runs before coverage starts. The name check reruns a
  test file that names the changed class, but not one whose provider calls it through a wrapper
  (`Wrapper::double()` → `Calc::double()`): it looks only one hop away.
- **`setUpBeforeClass()` / `#[BeforeClass]`** run outside any test's coverage. Test files that declare them are rerun
  on every source resume. The same code reached from a shared base class or a trait is not rerun.
- **Subprocesses.** Code a test runs in another process is not in its coverage. On every source resume these are
  rerun: test files that start one themselves (`shell_exec`, `exec`, `proc_open`, `Process`, `PhpProcess`,
  `Process::fromShellCommandline()`, backticks…), and test files with no recorded line at all. A test that starts the
  process through a helper, a trait or a base class (`tests/Support/…`), and also executes at least one recorded
  line, is not rerun.
- **Cross-file test dependencies the text rule misses.** A test file with `#[DependsExternal]`, `#[DependsOnClass]`,
  a cross-file `#[Depends]` or `@depends Other::test` means the full suite. A dependency declared in a base class or
  a trait, or written `@depends clone Other::test` / `@depends !clone Other::test` (annotations only, removed in
  PHPUnit 12), is not seen. When such a consumer is rerun, it is skipped and the merge asks for the full suite (see
  **Merge**); when it is not rerun (it never executes the changed code), it keeps its old result.
- **`tearDownAfterClass()` / `#[AfterClass]`** run outside any test's coverage, like `setUpBeforeClass()`, but they
  are not part of the text rule: an assertion there that runs changed code is not rerun.
- **Values computed once and cached across tests** by changed code. The first test that ran the code is recorded;
  later tests that use the cached value are not. Container compilation is covered by the `--full-glob` wiring paths;
  other warm-up code is not.
- **A failed kept run resumed with `--trust-junit-failures`**: a data provider error, a runner warning or a `failOn*`
  issue in a file that is not rerun is forgotten (reproduced: a real failure in `CalcTest` plus a provider error in
  `BrokenTest`; the fix of `Calc` resumes `CalcTest` only). Without the flag, a kept run whose tests failed always
  runs the full suite.
- **Coverage metadata hidden from a textual scan** (an attribute applied through a constant or a macro-like helper)
  is not seen.
- **A `Process` imported under an alias** (`use Symfony\Component\Process\Process as P; new P(…)`) is not seen as a
  subprocess; `Process::from…()`, `PhpProcess` and the fully qualified or plain `new Process` are.

That is why a resumed run:

- is stamped `coverage: resumed (k test files, m source files)`;
- may stand in for the full run for at most one green release.

It is also why the release proof should require a full run (`assert-release-proof --require-coverage=full`), with CI
or a nightly job running the full suite.

Exit codes: `0` OK (`plan`: resume; `status`: a passing saved run for the current files), `3` full suite needed,
`1` missing or unreadable data (`status`: unknown), `2` usage error.

### Makefile snippet: `coverage-resume.mk` (since 1.13.0)

```make
QUALITY_PHP_EXEC := docker compose exec -T php
COVERAGE_RESUME_FULL := test-coverage-full
COVERAGE_RESUME_FILES := test-coverage-files
COVERAGE_RESUME_OPTIONS := --always=tests/Gate/*
-include vendor/nowo-tech/php-quality-tools/resources/make/coverage-resume.mk
-include vendor/nowo-tech/php-quality-tools/resources/make/release-phases.mk

RELEASE_PHASES := check-fast quick=release-quick php-coverage=php-coverage-resume
release-check: release-phases
	@stamp=$$($(MAKE) -s coverage-resume-status) || { echo "release-check: $$stamp — not safe to tag"; exit 1; }; \
	echo "release-check OK — $$stamp — safe to tag."

test-coverage-full:   # the suite: per-test data, JUnit and Clover
	$(QUALITY_PHP_EXEC) vendor/bin/paratest --processes=2 --coverage-php $(COVERAGE_RESUME_COV) \
		--log-junit $(COVERAGE_RESUME_JUNIT) --coverage-clover $(COVERAGE_RESUME_CLOVER)
test-coverage-files:  # only $(FILES): per-test data and JUnit (here a host script that writes a git-ignored
                      # phpunit configuration listing $(FILES) and runs ParaTest on it)
	$(QUALITY_PHP_EXEC) sh scripts/paratest-files.sh 2 --coverage-php $(COVERAGE_RESUME_PART_COV) \
		--log-junit $(COVERAGE_RESUME_PART_JUNIT) $(FILES)
```

What `php-coverage-resume` does:

1. Plans, then either runs `COVERAGE_RESUME_FILES` (with `FILES=<test files>`) and merges, or runs
   `COVERAGE_RESUME_FULL`. A merge that refuses (another driver) falls back to the full suite in the same run.
2. Runs the gate (`COVERAGE_RESUME_GATE`, default `check-coverage-threshold <clover> 100 --metric=statements`),
   unless the tests failed.
3. Calls `save` with the tests' status and the overall status apart. A failed run is kept too: the next run resumes
   from it, or runs the full suite when the failure cannot be attributed.
4. Prints `php-coverage: passed|failed (tests …, gate …) — coverage: …`.

Options and related targets:

- **One run per project at a time**: the recipe holds `flock` on `$(COVERAGE_RESUME_DIR)/.lock` (when `flock(1)`
  exists), and a second run fails at once.
- **File list**: comes from `git ls-files -co --exclude-standard` on the host; `COVERAGE_RESUME_GIT_FILES=` turns it
  off.
- **Forcing the full suite**: `FORCE_COVERAGE=1`, `NO_RESUME=1` or `COVERAGE_RESUME=0`.
- **History rule**: `COVERAGE_RESUME_MAX_COMMITS=<n>` requires the kept run's commit to be an ancestor of `HEAD`, at
  most `n` commits back. It is off by default, because it also refuses the fix-below-the-release-commit flow.
- `make coverage-resume-status` prints the stamp of the saved run (or fails); `make coverage-resume-forget` drops it.

Put the stamp in the tag message on its own line, after the proof line (deploy guards match the proof line exactly):

```bash
git tag -a vX.Y.Z -m "Release vX.Y.Z" -m "release-check: OK $(git rev-parse HEAD)" \
  -m "release-check $(make -s coverage-resume-status)"
```

`assert-release-proof` then prints `OK: vX.Y.Z records a passing release-check for <sha> (coverage: full).`. With
`--require-coverage=full` (recommended for deploy guards), a tag whose coverage line is missing or says `resumed`
fails.

| Variable | Default |
| --- | --- |
| `COVERAGE_RESUME_FULL`, `COVERAGE_RESUME_FILES` | — (required: targets) |
| `COVERAGE_RESUME_DIR` | `var/coverage/resume` |
| `COVERAGE_RESUME_COV`, `COVERAGE_RESUME_JUNIT`, `COVERAGE_RESUME_CLOVER` | `var/coverage/coverage.cov`, `var/coverage/junit.xml`, `var/coverage/clover.xml` |
| `COVERAGE_RESUME_PART_COV`, `COVERAGE_RESUME_PART_JUNIT` | `var/coverage/resume-part.cov`, `var/coverage/resume-part.xml` |
| `COVERAGE_RESUME_GATE` | `check-coverage-threshold $(COVERAGE_RESUME_CLOVER) 100 --metric=statements` |
| `COVERAGE_RESUME_OPTIONS` | — (`bin/coverage-resume` options: `--src`, `--tests`, `--always`, `--max-src-files`, `--trust-junit-failures`…) |
| `COVERAGE_RESUME_GIT_FILES`, `COVERAGE_RESUME_MAX_COMMITS` | `1`, — |

The per-test data is larger than Clover, because each executable line lists its tests. Expect tens of MB for a suite
of thousands of tests, and expect `merge` / `save` to hold a few copies of it in memory: `bin/coverage-resume` raises
a lower `memory_limit` to `COVERAGE_RESUME_MEMORY_LIMIT` (default `2G`). Time and memory were measured on a 40-class
sample only; measure them on a large suite before relying on the resume there.

Both formats are read: php-code-coverage up to 11 (PHPUnit 10 / 11), and its later serialized format (PHPUnit 12+,
checked with PHPUnit 13.4 / php-code-coverage 14.5).

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
| `heavy.mk` | `heavy-status` | Slots with their groups, queue, load and memory gates | — |
| `heavy.mk` | — | `$(MEMORY_GUARD)` and `$(HEAVY_PROCESSES)` (2) for recipes | `MEMGUARD_*` (environment) |
| `quiet.mk` | `quiet T=<target>` | Any target through `quiet-run` (full log in `$(QUIET_LOG_DIR)/<T>.log`); `make heavy-quiet T=release-check` adds the lock | `QUIET_LOG_DIR` (`var/quiet`), `QUIET_JUNIT_CMD`, `QUIET_JUNIT_DIRS` |
| `quiet.mk` | `test-changed` | PHPUnit (no coverage) on `changed-tests`, then only the failures (`junit-failures`) | `TEST_CHANGED_PHPUNIT` (`--log-junit <file>` and the tests are appended), `BASE` |
| `release-phases.mk` | `release-phases` | Every `RELEASE_PHASES` entry (`<name>=<target>` or `<target>`) through `release-phase`, in order, stopping at the first failure; then the summary | `RELEASE_PHASE_FLAGS_<name>`, `RELEASE_PHASE_DIR`, `RELEASE_PHASES_TITLE`; `$(call release_phase,<name>,<command>)` in custom recipes |
| `release-phases.mk` | `check-changed-src-tested` | The hint above | `COVERAGE_HINT_STRICT=1` fails on a hit |
| `release-phases.mk` | `coverage-gaps`, `test-slowest` | The reports above | `COVERAGE_GAPS_REPORT` (`var/coverage/clover.xml`), `SLOWEST_TESTS_JUNIT` (`var/coverage/junit.xml`), `N` (30) |

A host that already defines one of these targets keeps its own by not including that snippet; the existing bins and
the 1.8 recipes above are unchanged.

## Shared test helpers (since 1.14.0)

Test classes that bundles used to copy from each other, in the `NowoTech\PhpQualityTools\Testing\` namespace. Every
helper is dependency-light: the package requires none of the libraries below, they are `suggest`ions that a class only
loads when you use it (`composer.json` lists them as `require-dev` of this repository, which is how the helpers are
tested).

| Helper | Needs | For |
| --- | --- | --- |
| `ConfigurationThreadStackTestCase` | `phpunit/phpunit`, `symfony/config` | configuration trees and sources that must load on a FrankenPHP thread stack |
| `WorkerMemoryAssertions` | `phpunit/phpunit` | flat-memory assertions over many requests through one kept-alive kernel |
| `RecordingLogger` | `psr/log` | in-memory PSR-3 logger, "no secret in the log" assertions |
| `SqliteManagerRegistry` | `doctrine/orm` ^3.0, `pdo_sqlite` | a real `ManagerRegistry` over in-memory SQLite managers |
| `TwigPathsPassTestCase` | `phpunit/phpunit`, `symfony/dependency-injection` | the contract of a bundle's `TwigPathsPass` |
| `Concurrency\SecondConnection` | `doctrine/dbal` ^4.0, MySQL / PostgreSQL server | a second database session in its own process |
| `Concurrency\TestManagerRegistry`, `Concurrency\RetainingRepositoryFactory` | `doctrine/orm` ^3.0 | manager resets as in a worker, repositories kept across them |

### Adopting without config files

The package is a Composer plugin that installs configuration files (Rector, PHP-CS-Fixer…) in the host. A bundle that
only wants the helpers and bins disables the plugin and requires the package as a development dependency:

```json
{
    "require-dev": {
        "nowo-tech/php-quality-tools": "^1.14",
        "doctrine/orm": "^3.0",
        "psr/log": "^3.0",
        "symfony/config": "^6.4 || ^7.0 || ^8.0"
    },
    "config": {
        "allow-plugins": {
            "nowo-tech/php-quality-tools": false
        }
    }
}
```

With `allow-plugins` set to `false` Composer never runs the plugin, so nothing is written to the project; `autoload`
and `bin` still work (`vendor/bin/check-demo-lock`, …). Require only the libraries of the helpers you use. Adopting a
helper in a bundle means: require the package as above, delete the bundle's own copy of the class, point its tests at
the shared class (a `use` change, or a thin subclass for the `*TestCase` classes) and run the suite.

### `ConfigurationThreadStackTestCase`

PHP 8.3+ stops the compiler at `zend.max_allowed_stack_size`; a FrankenPHP thread on Alpine has a 512 KiB stack, so a
file that needs more cannot be loaded there. One long fluent chain in a configuration tree needs about 1 KiB of stack
per call. The test case runs PHP in a child process with the stack capped at the thread size and checks that every
configuration class loads, builds, processes its defaults and dumps its YAML and XML references (the child's output
must equal the parent's), and that every file under the source directory compiles (`php -l`).

```php
use NowoTech\PhpQualityTools\Testing\ConfigurationThreadStackTestCase;

#[CoversClass(Configuration::class)]
final class ConfigurationThreadStackTest extends ConfigurationThreadStackTestCase
{
    protected static function configurationClasses(): array
    {
        return ['Configuration' => Configuration::class, 'RuntimeConfiguration' => RuntimeConfiguration::class];
    }

    protected static function sourceDirectory(): string
    {
        return __DIR__ . '/../../../src';
    }
}
```

`autoloadFile()` (the host's `vendor/autoload.php`, found from Composer's class loader) and the `THREAD_STACK` constant
(`512K`) can be overridden. Before PHP 8.3 the cap does not exist: the tests then only check load, build, process and
compile.

### `WorkerMemoryAssertions`

Drive ONE kernel through `http_kernel->handle()` / `terminate()` many times (worker mode, kernel reset off), sample the
heap after a GC run every 50 requests after a 50-request warm-up and assert that the growth (last sample minus first,
as the per-bundle copies do) stays below 4 MiB. The effective margin is that growth over all the sampled requests: with
1000 requests and the default sampling, 4 MiB over 900 requests. `medians: true` compares instead the medians of the
first and the second half of the samples, which ignores one late reallocation step of a long-lived table while a leak
(raising every later sample) still moves the median.

```php
$samples = [];
for ($i = 0; $i < 1000; ++$i) {
    $this->listing($kernel, $i % 2 === 0 ? 'alice' : 'bob');
    WorkerMemoryAssertions::sample($i, $samples);
}
WorkerMemoryAssertions::assertFlat($samples);                    // last - first < 4 MiB; assertFlat($samples, 1024 * 1024) for another margin
WorkerMemoryAssertions::assertFlat($samples, medians: true);     // or the median-halves mode
```

`sample()`, `growth($samples, $medians = false)`, `median()` and `collectedMemory()` are public static, for assertions of your own.

### `RecordingLogger`

A final `Psr\Log\AbstractLogger` that keeps every record in `$records` (`level`, `message`, `context`), with
`messages()`, `levels()`, `lines()` (`"level: message"`), `recordsWithLevel()`, `has($fragment)`, `contextValues()` (every
scalar context value, objects as their type), `dump()` (everything as JSON, to assert a secret never appears) and
`clear()`.

```php
$logger = new RecordingLogger();
$service = new Mailer($logger);
$service->send($message);
self::assertStringNotContainsString($password, $logger->dump());
```

### `SqliteManagerRegistry`

A real `ManagerRegistry` over in-memory SQLite entity managers, one connection per manager. Each manager maps only the
namespaces given to it (attribute mapping); `resetManager()` replaces the manager and keeps the connection, as the
Doctrine bundle does in a worker; optional `loadClassMetadata` listeners apply to every manager.

```php
$registry = new SqliteManagerRegistry(
    ['default' => ['App\Entity' => __DIR__ . '/../../src/Entity'], 'audit' => ['App\Audit' => __DIR__ . '/../../src/Audit']],
    default: 'default',
    metadataListeners: [new TablePrefixListener('app_')],
);
$em = $registry->getManager('audit');
(new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
```

### `TwigPathsPassTestCase`

The contract of a bundle's `TwigPathsPass`: without a native Twig loader it does nothing; the loader is found as
`twig.loader.native` or `twig.loader.native_filesystem` through any alias chain; the bundle views are added last with
`addPath(<views>, <namespace>)`; `<project>/templates/bundles/<namespace>` is prepended first, only when it exists.

```php
#[CoversClass(TwigPathsPass::class)]
final class TwigPathsPassTest extends TwigPathsPassTestCase
{
    protected function passClass(): string
    {
        return TwigPathsPass::class;
    }

    protected function twigNamespace(): string
    {
        return 'NowoExampleBundle';
    }

    protected function viewsDirectory(): string
    {
        return dirname(__DIR__, 4) . '/src/Resources/views';
    }

    // Only when the pass is gated by a parameter (a feature flag): protected function parameters(): array
}
```

Bundle-specific cases (a disabled feature, a second legacy namespace) stay as extra test methods in the subclass.

### Two-connection race tests: `Testing\Concurrency\`

- `SecondConnection`: a second database session in its own PHP process (`resources/testing/second-connection.php`, loaded
  from the host's autoloader). `execute($sql)` answers `OK` or `ERR <driver code> <SQLSTATE>`, `send($sql)` +
  `await()` run a statement that blocks on a lock, `sleep($ms)` queues a wait (put it before a `COMMIT` to release a lock
  while the test is blocked), `value($sql)` returns `VAL '<first value>'`, `close()` ends it. Waits are bounded (15 s lock
  timeout; 90 s read timeout). URLs: `mysql://…`, `pgsql://…` (also `sqlite:///:memory:` for the helper's own tests).
  The URL (it carries the password) reaches the helper process through the `PQT_SECOND_CONNECTION_URL` environment variable,
  not its arguments, so it is not in the process list. The protocol is one command per line, so the SQL must be a single
  line: a statement with a line break throws `InvalidArgumentException` instead of running its tail as another command.
- `TestManagerRegistry`: one connection and one entity manager built by a closure; `resetManager()` builds a new manager
  on the same connection and counts it in `$resets`.
- `RetainingRepositoryFactory`: keeps one repository per manager instance for the life of the process (what a container
  repository factory does), `managers()` counts the instances: the reset test proves that no repository obtained before a
  reset is used after it.

```php
$other = new SecondConnection(getenv('DATABASE_URL'));
$other->execute('START TRANSACTION');
$other->execute('UPDATE account SET balance = 0 WHERE id = 1');
$other->send('UPDATE account SET balance = 1 WHERE id = 1');   // second update: blocks on the lock
// … the test's own connection commits or rolls back, the service under test retries …
self::assertSame('OK', $other->await());
$other->close();
```

## Bundle repository checks (since 1.14.0)

Four CI checks that every bundle repository carried as a script of its own, now bins of this package (PHP, no Python or
Bash needed on the runner; each takes `--root=<dir>` or `<bundle-dir>`, `--help`, and exits 0 OK / 1 violations / 2
usage). Adopt them as in [Adopting without config files](#adopting-without-config-files) and replace the call of the
repository script by the bin.

### `check-demo-lock`

```bash
vendor/bin/check-demo-lock [<bundle-dir>] [--only=<glob>]...
```

A demo installs the bundle from a path repository with `composer install`, i.e. from its lock, which stores the
bundle's requirements as they were when the lock was last updated: a requirement added or raised later is not installed
and the demo fails with "class not found". The bin reports, for every `vendor/package` requirement of the bundle's
`composer.json` (`--only` narrows them, fnmatch, repeatable), the version each `demo/<name>/composer.lock` resolves and
fails with `STALE <lock>: <package> <constraint> -> <version|not installed>` when it does not satisfy the constraint. A
demo without a lock (git-ignored, resolved at install) is reported and passes. Invalid JSON in `composer.json` or a lock,
or an empty `--only=`, exits 2.

*Why PHP and not the Python script with a wrapper:* the package and its CI recipes are PHP only (`bin/autoload.php`
falls back to a bare clone), a wrapper would add a Python dependency to every runner and a second language to test, and
the logic is small. The constraint matcher (`Gate\DemoLock\ConstraintMatcher`: caret, tilde, comparison operators,
`1.2.*`, `||`, AND by space or comma, `dev-*`) does not need `composer/semver`. Differences from the script it replaces:
`^0.0.3` is bounded at `<0.0.4` and `^0.3.5` at `<0.4.0` as Composer does, `1.2.*` wildcards and `>= 1.0` with a blank
are understood, `<=` is supported.

### `check-no-raw-html-form`

```bash
vendor/bin/check-no-raw-html-form [--root=<dir>] [--dir=<dir>]... [--allow=<path>]...
                                  [--allow-form-themes] [--allow-themes] [--allow-ignore-marker]
```

Fails on raw `<form>` / `<input>` / `<textarea>` / `<select>` in Twig (REQ-TWIG-005): render forms through Symfony Form
(`form_start`, `form_row`, `form_widget`, `form_end`). Scans `src/Resources/views` and every `demo/**/templates`
directory (`src/Resources/views` and the `templates` directories of `demo/<name>/` and `demo/<a>/<b>/` only; `vendor`,
`node_modules`, `var` and dot-directories are skipped, so git-ignored demo data folders are never entered) or the
`--dir` directories. A directory that cannot be read exits 2 ("cannot read a directory"). **Strict by default: no exception.**
Matching is case-insensitive; violations print as `file:line:content`. Exit 2 (not 0) when the scanned directories hold
no `*.twig` file, so a wrong path never passes silently.

Exceptions are opt-in, one flag each:

| Flag | Exempts |
| --- | --- |
| `--allow-form-themes` | `src/Resources/views/form/` and `src/Resources/views/Form/` (form themes that emit the controls) |
| `--allow-themes` | every file whose name contains `theme` |
| `--allow-ignore-marker` | the control on the line below `{# twig-005-ignore: <reason> #}`; the `: <reason>` is mandatory |
| `--allow=<path-or-glob>` | one file or a glob (repeatable; `*` also crosses `/`, so `--allow='demo/*/templates/form/*'` covers a directory); an entry that matches nothing is a warning |

Which options reproduce each family of repository scripts it replaces:

| Script family | Options |
| --- | --- |
| no exceptions (the strictest copies) | none |
| form themes under `Form/` / `form/` only | `--allow-form-themes` |
| form themes by name plus the ignore marker (the theme + marker copies) | `--allow-form-themes --allow-themes --allow-ignore-marker` (drop `--allow-form-themes` when the copy did not exempt the directory) |
| an explicit list of legacy files | one `--allow=<path>` per file |
| a demo directory of form templates (e.g. `demo/*/templates/form/`) | `--allow='demo/*/templates/form/*'` |

### `check-twig-extra`

```bash
vendor/bin/check-twig-extra [--root=<dir>] [--forbid-raw=<dir>]...
```

REQ-TWIG-004: when the bundle has any `*.twig` file under `src/`, `templates/` or `Resources/`, its `composer.json` must
name `twig/extra-bundle` and `twig/string-extra`, and every demo that boots `TwigBundle` (`demo/<name>/` or
`demo/<a>/<b>/`) must name `twig/extra-bundle` in its `composer.json` and register `TwigExtraBundle` in
`config/bundles.php`. `--forbid-raw=<dir>` also fails on the `|raw` filter outside Twig comments in the templates under
`<dir>` (for bundles that print HTML through a sanitising filter); a `<dir>` that does not exist exits 2.

### `check-translation-key-parity`

```bash
vendor/bin/check-translation-key-parity [--root=<dir>] [--dir=<dir>] [--views=<dir>] [--domain=<name>]...
                                        [--locales=<en,es,...>] [--reference=<locale>] [--no-twig]
```

REQ-I18N-002: the `<Domain>.<locale>.yaml` catalogues of a domain (default directory `src/Resources/translations`, every
domain that has a reference-locale file unless `--domain` names them) must have the same nested keys in every required
locale (default `en,es,it,fr,pt,de,nl`; reference `en`), and every literal key a Twig template under `--views` (default
`src/Resources/views`) translates in the domain, through `'key'|trans` with `trans_default_domain` or an explicit domain
argument, must exist in the reference catalogue. Keys are flattened from the YAML indentation (no YAML parser needed);
a domain given as a variable is skipped. `.yaml` and `.yml` files are read. Without a translations directory, or with one
that holds no catalogue, the check is skipped (an explicit `--dir` or `--domain` with a missing directory exits 2); a directory with catalogues but none for the reference locale fails (exit
1). Name the domains with `--domain` in CI: that also makes a missing reference file an error for that domain.

## Test hygiene gates (since 1.11.0)

Two more static gates, harvested from the Symfony Beacon 1.122.0 release audit (KIT-76): its release-check failed late
for reasons a seconds-long scan catches. Plain PHP, same conventions as the other gates (`OK …` on stdout, findings on
stderr, exit 0 OK, 1 findings, 2 usage error). Like every gate they run only where you call them, so upgrading changes
nothing until you wire them into `make qa` / `check-fast` / `release-precheck`.

### `check-coverage-annotations`

```text
vendor/bin/check-coverage-annotations [--root=<dir>] [--paths=<dir>]... [--exclude=<pattern>]... [--no-match]
```

- **Ignored annotations.** php-code-coverage honours a line annotation only when the whole `//` comment is exactly
  `// @codeCoverageIgnore`, `// @codeCoverageIgnoreStart` or `// @codeCoverageIgnoreEnd` (it compares the trimmed comment
  token). `// @codeCoverageIgnore because …`, a `#` or `/* … */` comment, or a double space after `//` is dropped
  without a warning, so the lines count as uncovered and the 100 % gate fails at the very end. Every other non-doc
  comment that mentions `@codeCoverageIgnore` is reported; write the reason in a comment on the line above. Docblock
  (`/** … */`) annotations are not line annotations and are not checked; mind that a class or method docblock that
  merely mentions `@codeCoverageIgnore` (e.g. in prose) excludes that whole class or method from coverage.
- **Unmappable `match`.** A `match (…)` that is the operand of `?`, `?:`, `??` or follows `:` (ternary else branch, named
  argument) cannot be mapped to lines by the coverage driver. Assign the `match` to a variable first. `--no-match` turns
  this check off.
- Paths: `--paths` (repeatable; a directory or a PHP file), default `src/` when it exists. `--exclude`: a path, a
  directory prefix or an `fnmatch` glob. `vendor/` and `node_modules/` are always skipped. Token based: strings never match.

```text
$ vendor/bin/check-coverage-annotations
error: src/Ingest/Otlp/OtlpDecoder.php:88 "// @codeCoverageIgnoreStart protobuf extension missing" is ignored by php-code-coverage (write exactly "// @codeCoverageIgnore[Start|End]", the reason on the line above)
error: src/Issues/Service/IssueTitle.php:41 match() after "?" cannot be mapped by the coverage driver (assign the match to a variable first)
Coverage annotations: 2 violation(s).
```

### `check-wallclock-fixtures`

```text
vendor/bin/check-wallclock-fixtures [--root=<dir>] [--paths=<dir>]... [--exclude=<pattern>]...
                                    [--baseline=<file>] [--write-baseline] [--frozen-marker=<regex>]... [--warn-only]
```

Tests and fixtures that read the real clock pass today and fail tomorrow, at midnight, on the first of the month or in
another time zone. The gate reports, in files that do not freeze the clock:

| Language | Reported | Not reported |
| --- | --- | --- |
| PHP | `new DateTime()` / `new DateTimeImmutable()` / `new Carbon()` / `date_create()` without an argument or with a relative literal (`'now'`, `'today'`, `'tomorrow'`, `'yesterday'`, `'midnight'`, `'noon'`, `'next …'`, `'last …'`, `'this …'`, `'+1 day'`, `'3 weeks ago'`, weekday names); `strtotime('<relative>')` without a base timestamp; `time()`; `date()` / `gmdate()` / `idate()` without a timestamp; `Carbon::now()` / `today()` / `tomorrow()` / `yesterday()` | absolute dates (`'2026-01-15'`, `'@1700000000'`, a phrase with a year such as `'first monday of january 2026'`), variable or computed arguments, `date('Y', $ts)`, `strtotime('+1 day', $base)`, methods (`$clock->time()`), static calls of other classes, namespaced functions, strings and comments |
| JS / TS | `new Date()` without an argument | `new Date('2026-01-01')`, `Date.now()` (usually durations), `//` and `/* … */` comment lines |

- **Frozen clock = file skipped.** PHP: `MockClock`, `ClockSensitiveTrait`, `mockTime(`, `ClockMock`, `time-sensitive`
  (PHPUnit bridge group), `FrozenClock`, `setTestNow(`, `Clock::set(`. JS / TS: `useFakeTimers(`, `setSystemTime(`,
  `mockdate` / `MockDate`, `timekeeper`, `@sinonjs/fake-timers`, `clock.install(` / `clock.setFixedTime(` (Playwright).
  `--frozen-marker=<regex>` (PCRE with delimiters, repeatable) adds a project helper, e.g. `--frozen-marker='/freezeTime\(/'`.
- **Inline opt-out.** `@wallclock-ignore` in a comment at the end of the line, or in a comment alone on the line above,
  skips that one finding (say why: `// @wallclock-ignore elapsed-time measurement`).
- **Files.** Every PHP file under `--paths` (default `tests/` when it exists; add e.g. `--paths=src/DataFixtures` for
  Doctrine fixtures), and the JS / TS test files: `*.test.*`, `*.spec.*`, or below a `tests`, `test`, `__tests__`, `e2e`,
  `fixtures` or `__fixtures__` directory (`--paths=assets` scans `assets/**/*.test.ts`, not the sources). A file passed
  explicitly in `--paths` is always scanned. `vendor/`, `node_modules/`, `var/` and `*.d.ts` are skipped.
- **Ratchet.** `--baseline=<file>` lists files (one root-relative path per line, `#` comments) that may keep their
  findings: they print `warning: … [baseline]` and the gate still passes, while any other file fails. A listed file
  without findings (fixed, renamed or deleted) is an error, so the list only shrinks. `--baseline=<file> --write-baseline`
  writes the current offenders (adoption). `--warn-only` prints every finding as a warning and exits 0. The baseline
  must be a file inside the project root: `..` is refused, and `--write-baseline` never writes through a symlink or
  into a symlinked directory that leads outside the root.
- **Start with a baseline.** An existing suite usually has many findings (Symfony Beacon had 666 at 1.122.0), so the
  first run on a project is `--write-baseline` (or `--warn-only` while you look), never the plain gate.
- **Fails closed.** An unreadable file or directory under the scanned paths is `ERROR: cannot read …`, exit 2 (also with
  `--warn-only`), never a silent pass; fix its permissions or `--exclude` it. The same holds for
  `check-coverage-annotations`. Both tokenise whole files (about 10× the file size in memory): `--exclude` multi-MB
  generated fixtures when PHP runs with a low `memory_limit`.

```text
$ vendor/bin/check-wallclock-fixtures --baseline=tests/wallclock-baseline.txt
warning: tests/Functional/ReportExportTest.php:31 new DateTimeImmutable('yesterday') reads the wall clock (freeze the clock: MockClock / ClockSensitiveTrait::mockTime(), or use a fixed date; @wallclock-ignore to opt out) [baseline]
error: tests/Unit/Retention/PurgeWindowTest.php:18 new DateTimeImmutable('-30 days') reads the wall clock (…)
Wall-clock fixtures: 1 error(s), 1 warning(s).
```

### Enabling them

```makefile
# Seconds-long static checks: put them in front of the slow suites.
check-fast: ensure-up
	$(DC) exec -T php vendor/bin/check-coverage-annotations
	$(DC) exec -T php vendor/bin/check-wallclock-fixtures --paths=tests --paths=assets --baseline=tests/wallclock-baseline.txt
	$(DC) exec -T php vendor/bin/check-module-boundaries      # with require_all_declared: true in the config
```

Adopting the wall-clock gate on an existing suite: run `vendor/bin/check-wallclock-fixtures --paths=tests --paths=assets --baseline=tests/wallclock-baseline.txt --write-baseline`
once (with the same `--paths` as the recurring call), commit the file, and remove lines as tests get a frozen clock.
