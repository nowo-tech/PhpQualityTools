# Feature Specification: Gate scripts and SQL query budgets

**Feature Branch**: `main`  
**Created**: 2026-10-13  
**Status**: Released in 1.2.0  
**Input**: Symfony Beacon spec `137-kit-small-extractions`, FR-008 (KIT-QA-SCRIPTS): extract Beacon's `.scripts/check-coverage-threshold.sh`, `.scripts/check-module-boundaries.sh` and the query counting of `tests/Integration/QueryBudget/HotPathQueryBudgetTest.php` into this package. Beacon's boundary map and budgets stay in Beacon as config.

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#gate-scripts-since-120), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-120)

---

## User Scenarios & Testing

### User Story 1 — Coverage gate in CI (Priority: P1)

As a maintainer, I run `vendor/bin/check-coverage-threshold var/coverage/clover.xml` with `COVERAGE_MIN=100`, so CI fails when statement or line coverage drops.

**Acceptance Scenarios**:

1. **Given** a Clover report at 75 % statements / 90 % lines, **When** the minimum is 80, **Then** exit 1 with `ERROR: Statement coverage 75.00% is below threshold 80.00%`.
2. **Given** no minimum (argument, `--min`, `COVERAGE_MIN`), **When** the gate runs, **Then** coverage is printed and it exits 0.
3. **Given** a missing report, **Then** exit 1; an unparsable report or a missing `project/metrics`, **Then** exit 2.

### User Story 2 — Module boundaries as config (Priority: P1)

As an architect, I describe modules and forbidden imports in `config/quality/module-boundaries.yaml`, so a new `use` from `App\Shared` to `App\Project` (outside the reviewed exceptions) fails CI.

**Acceptance Scenarios**:

1. **Given** Beacon's map (`tests/Fixtures/module-boundaries/beacon.yaml`) and a tree satisfying it, **Then** exit 0.
2. **Given** each regression the former shell script caught (file moved, forbidden import, OTLP controller outside `Ingest/Otlp`, missing required file, `OneToMany` without `EXTRA_LAZY` …), **Then** exactly one violation and exit 1.
3. **Given** an unknown key, unknown module or invalid regex, **Then** exit 2 with the config path and key in the message.

### User Story 3 — Query budgets and N-scaling (Priority: P2)

As a developer, I `use QueryBudgetTrait` in a PHPUnit test, so a page over its SQL budget, or whose query count grows with the number of rows, fails.

**Acceptance Scenarios**:

1. **Given** an operation running 3 statements, **When** `assertQueryBudget(1, …)`, **Then** the failure lists the 3 statements.
2. **Given** an N+1 list, **When** `assertScalesWith()` with sizes `[5, 50]`, **Then** the failure reports `6 for N=5, 51 for N=50`.
3. **Given** the kernel (and so the counter service) is replaced during the operation, **Then** only the new counter's statements are counted.

---

## Requirements

### Coverage gate (`src/Gate/Coverage/`, `bin/check-coverage-threshold`)

- **FR-GATE-COV-001**: Read a Clover XML file: statements from `project/metrics`; lines as distinct `<line type="stmt|cond">` per file + number (covered when `count > 0`).
- **FR-GATE-COV-002**: Minimum from the second argument, `--min=N`, or `COVERAGE_MIN`; no minimum → informational exit 0. Minimum must be numeric within 0–100 (else exit 2).
- **FR-GATE-COV-003**: `--metric=both` (default) / `statements` / `lines`; a report without `<line>` entries skips the line check, and `--metric=lines` then exits 2.
- **FR-GATE-COV-004**: Exit codes: 0 OK, 1 below threshold or missing report, 2 usage / parse error. Percentages truncated to 2 decimals.

### Module boundaries (`src/Gate/ModuleBoundary/`, `bin/check-module-boundaries`)

- **FR-GATE-MB-001**: Config from `--config` or `config/quality/module-boundaries.{yaml,yml,php}` under `--root` (default: current directory). YAML via `symfony/yaml` when installed; PHP file returning an array always.
- **FR-GATE-MB-002**: Keys `paths`, `ignore`, `references` (`use` | `all`), `modules` (name → `namespace` + optional `path`, or namespace string), `allowed_edges`, `forbidden_edges` (`from`, `to`, `except`, `message`), `rules`. Unknown keys / modules and invalid regexes are config errors (exit 2).
- **FR-GATE-MB-003**: A file's module = longest matching `path`; an imported name's module = longest matching `namespace` (case-insensitive). Self imports and names outside every module are ignored.
- **FR-GATE-MB-004**: Imports are extracted from tokens: top-level `use` (grouped, `function`, `const`); not trait `use` nor closure `use`. With `references: all`, fully-qualified names in code too.
- **FR-GATE-MB-005**: Rule types `forbid_path`, `require_path`, `forbid_import`, `forbid_pattern`, `require_pattern`, `require_near` (see `Rule`). Regexes are PCRE without delimiters, multi-line.
- **FR-GATE-MB-006**: Path patterns: exact path, directory prefix, or `fnmatch` glob when containing `*`, `?`, `[`.
- **FR-GATE-MB-007**: Output `error: <message>` + indented `file:line` details on stderr; `OK: module boundaries (…)` on stdout. Exit 0 / 1 / 2.
- **FR-GATE-MB-008**: Beacon's former shell rules are expressible exactly; the translation is the `beacon.yaml` test fixture.

### Query budgets (`src/Testing/`)

- **FR-QB-001**: `Testing\Dbal\QueryCountingMiddleware` (DBAL 4) records `query()`, `exec()` and prepared-statement `execute()` SQL in a `QueryCounter`; transaction statements only when enabled.
- **FR-QB-002**: `QueryBudgetTrait` requires `getQueryCounter(): QueryCounter` and exposes `recordQueries()`, `countQueries()`, `assertQueryBudget(int $max, callable, string $message = ''): int`, `assertScalesWith(callable $prepare, callable $operation, array $sizes = [5, 50], int $tolerance = 0, bool $warmUp = true, string $message = ''): array`.
- **FR-QB-003**: `assertScalesWith()` calls `$prepare(N, previousN)` for strictly increasing sizes (≥ 2), optional unmeasured warm-up after the first preparation, and fails when `max - min > tolerance`.
- **FR-QB-004**: `doctrine/dbal`, `phpunit/phpunit` and `symfony/yaml` stay out of `require` (suggest / require-dev only).

### Compatibility

- **FR-COMPAT-001**: The Composer plugin (`Plugin`) behaviour is unchanged; the new tools run only when invoked.
- **FR-COMPAT-002**: `bin/`, `src/Gate/` (one-shot CLI processes) and `src/Testing/` (PHPUnit-only, test container) are never worker-mode services, so they are excluded from the Igor audit in `igor.json` (REQ-CS-008); their per-run state (`ModuleBoundaryChecker` caches, `QueryCounter` log) is intentional.

---

## Success Criteria

- **SC-001**: 100 % PHP line coverage including every new class (`make test-coverage`).
- **SC-002**: `make release-check` passes (composer validate, CS, Rector, PHPStan level 8, tests).
- **SC-003**: Beacon's map passes on Beacon's tree (checked manually against Beacon 1.59.x before release) and every rule fails on its fake-tree regression in `ModuleBoundaryCheckerTest`.

## Out of scope

- Beacon adoption (Beacon spec 137 Phase 2) and Beacon's own budgets / scenarios.
- DBAL 3 support for the middleware.
