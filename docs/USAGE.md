# Usage

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
