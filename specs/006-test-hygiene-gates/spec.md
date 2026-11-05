# Maintainer: Héctor Franco Aceituno (@HecFranco)
# Organization: nowo-tech (https://github.com/nowo-tech)

# Feature Specification: Test hygiene gates and budget warnings

**Feature Branch**: `feat/beacon-gates-1122`  
**Created**: 2026-11-05  
**Status**: Planned for 1.11.0  
**Input**: Symfony Beacon request `docs/ops/BUNDLES-REQUESTS/2026-11-05-php-quality-tools-gates.md` (audit of v1.122.0, `AUDIT-2026-11-05.md` KIT-76; findings REL-COVERAGE-ANNOT, REL-WALLCLOCK-FIXTURES, REL-MODULE-DECL-LATE, REL-BUDGET-LATE; OTHER candidates REQ-QA-020 … 022, REQ-FE-001). The 1.122.0 release-check failed late for reasons a seconds-long static check would have caught.

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#test-hygiene-gates-since-1110), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#unreleased)

---

## User Scenarios & Testing

### User Story 1 — Coverage annotations that work (Priority: P1)

As a maintainer, `check-fast` fails when a coverage annotation will be ignored by php-code-coverage, instead of the 100 % coverage gate failing at the end of the release-check.

**Acceptance Scenarios**:

1. **Given** `// @codeCoverageIgnoreStart because …` in `src/`, **When** `check-coverage-annotations`, **Then** exit 1 and `error: <file>:<line> "…" is ignored by php-code-coverage (…)`.
2. **Given** exactly `// @codeCoverageIgnore` (or `//@codeCoverageIgnoreEnd`), a docblock annotation, or the text inside a string, **Then** no finding.
3. **Given** `$a ? match ($x) {…} : null`, `?: match`, `?? match` or `f(name: match (…))`, **Then** exit 1 (`match() after "?" …`); `$r::match()`, `$o->match()` and a `match` assigned to a variable pass. `--no-match` skips the check.

### User Story 2 — Tests that do not depend on today (Priority: P1)

As a maintainer, a test that builds `new DateTimeImmutable('tomorrow')` or calls `time()` without a frozen clock fails the fast gate, while the existing offenders are tracked in a ratchet file that only shrinks.

**Acceptance Scenarios**:

1. **Given** `tests/X.php` with `new DateTimeImmutable('tomorrow')` and no frozen-clock marker, **Then** exit 1, `error: tests/X.php:<line> new DateTimeImmutable('tomorrow') reads the wall clock (…)`.
2. **Given** the same file with `ClockSensitiveTrait` / `MockClock` / `mockTime(` (or a JS test with `vi.useFakeTimers()`), **Then** no finding.
3. **Given** `// @wallclock-ignore <reason>` at the end of the line or alone on the line above, **Then** that finding is skipped.
4. **Given** `--baseline=f` listing the file, **Then** `warning: … [baseline]`, exit 0; a new file fails; a listed file without findings fails (`remove it from the baseline`).
5. **Given** `new DateTimeImmutable('2026-01-15')`, `date('Y', $ts)`, `$clock->time()`, `strtotime('+1 day', $base)`, `Date.now()`, **Then** no finding (low false-positive rate).

### User Story 3 — Every module declared (Priority: P2)

As a maintainer, a new `src/Billing/` directory that nobody added to `module-boundaries.yaml` fails at once with `require_all_declared: true`.

### User Story 4 — Budget early warning (Priority: P2)

As a maintainer, `check-asset-budgets --warn-at=90` shows which entries are about to break their budget without failing the build.

---

## Requirements

- **FR-COVA-001**: `bin/check-coverage-annotations` (`Gate\Coverage\CoverageAnnotationsCommand`). Token based. A `T_COMMENT` (not `T_DOC_COMMENT`) containing `@codeCoverageIgnore` whose trimmed text is not exactly `// @codeCoverageIgnore`, `//@codeCoverageIgnore` or their `Start` / `End` forms is a violation (php-code-coverage compares the trimmed comment token).
- **FR-COVA-002**: A `T_MATCH` token preceded (ignoring whitespace and comments) by `?`, `:` or `??` and followed by `(` is a violation; `--no-match` disables it.
- **FR-COVA-003**: `--paths` (repeatable, directory or file; default `src` when it exists), `--exclude` (path, prefix or `fnmatch` glob), `--root`; `vendor/` and `node_modules/` skipped. Exit 0 (`OK: coverage annotations (N PHP file(s)).`), 1 (`error: <file>:<line> …` lines and a count on stderr), 2 usage (unknown / empty option, missing path or root).
- **FR-WC-001**: `Gate\WallClock\WallClockScanner::scanPhp()`: token based. Reports `new DateTime|DateTimeImmutable|Carbon|CarbonImmutable` without parentheses, without arguments or with a relative single literal first argument; `date_create[_immutable]()` likewise; `strtotime(<relative literal>)` with one argument; `time()` without arguments; `date|gmdate|idate()` with one argument; `Carbon[Immutable]::now|today|tomorrow|yesterday()`. Methods, static calls of other classes, declarations, constants, named arguments and qualified function names are not calls of the global function.
- **FR-WC-002**: Relative literal: empty, or a word `now|today|tomorrow|yesterday|midnight|noon|next|last|this|ago` or a weekday, or a leading `[+-]N unit`; unless it also holds a calendar date, `@timestamp` or a year 1900–2099. `scanJs()`: `new Date()` without argument, skipping `//` comments and comment lines.
- **FR-WC-003**: A file matching a frozen-clock marker (PHP: `MockClock`, `ClockMock`, `ClockSensitiveTrait`, `FrozenClock`, `mockTime(`, `setTestNow(`, `Clock::set(`, `time-sensitive`; JS: `useFakeTimers(`, `setSystemTime(`, `mockdate`, `MockDate`, `timekeeper`, `fake-timers`, `clock.install(`, `clock.setFixedTime(`) or an extra marker has no finding. `@wallclock-ignore` in a comment on the finding's line, or in a comment alone on the line above, skips it.
- **FR-WC-004**: `bin/check-wallclock-fixtures` (`Gate\WallClock\WallClockFixturesCommand`): every `.php` under `--paths` (default `tests` when it exists), JS / TS (`js mjs cjs jsx ts mts cts tsx`, not `.d.ts`) when named `*.test.*` / `*.spec.*`, below `tests|test|__tests__|e2e|fixtures|__fixtures__`, or passed explicitly; `vendor/`, `node_modules/`, `var/` skipped; `--exclude`; `--frozen-marker` (validated PCRE).
- **FR-WC-005**: `--baseline=<file>`: listed files' findings are warnings; listed files without findings (or not scanned) are errors; missing file exit 2. `--write-baseline` writes the files with findings (header comment) and exits 0. `--warn-only` turns every finding into a warning. Exit 0 (`OK: wall-clock fixtures (N file(s) scanned, W warning(s), B baselined file(s)).`), 1 errors, 2 usage.
- **FR-MB-DECL-001**: `module-boundaries` config key `require_all_declared`: `false` (default), `true` (= `[src]`) or a list of root directories (not the project root). Every directory directly under a root must match a module `path` (exact or ancestor) or an `ignore` pattern; a missing root is reported. One violation `Undeclared module directories (require_all_declared; …):` with the directories as details.
- **FR-AB-WARN-001**: `check-asset-budgets --warn-at=<percent>` and config key `warn_at` (the option wins): `90`, `90%`, `87.5`; above 0 and at most 100, else exit 2. A check at or above the share and not over budget prints `WARN … (<pct>% >= <warn>%)` (percent truncated to one decimal) and exits 0; the summary adds `, N warning(s) at >= <warn>%`. Without a threshold the output is byte-for-byte unchanged.

### Compatibility

- **FR-COMPAT-001**: Every gate and key is opt-in: existing scripts, configs and outputs are unchanged unless the new options / keys are used. No new `require`. MINOR release (1.11.0). `bin/heavy-lock` unchanged.

## Success Criteria

- **SC-001**: 100 % PHP line coverage of the new and changed classes; positive and negative fixtures for every gate.
- **SC-002**: CI green (tests on the PHP matrix, cs-check, PHPStan, coverage).
- **SC-003**: Integration tests run the two new binaries in a child process.

## Out of scope

- Wiring the gates into Beacon's `check-fast` / `release-precheck` and deleting its own scripts (follow-up in the app).
- `DateTimeImmutable::createFromFormat()` without a time part (takes the current time of day) and `Date.now()` in JS.
