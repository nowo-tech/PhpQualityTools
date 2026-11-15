# Feature Specification: Coverage resume for small source fixes (`bin/coverage-resume`)

**Feature Branch**: `feat/coverage-resume-src`  
**Created**: 2026-11-15  
**Status**: Planned for 1.13.0  
**Input**: Symfony Beacon request (owner-approved): extend the release gate's "php-coverage resume" (decision
D-20261112-04, tests-only changes) to small `src/` fixes. Today any `src/` change after a failed coverage gate means a
full run of about 70–90 minutes.

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#coverage-resume-since-1130), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#unreleased)

---

## Starting point

The resume shipped in Beacon (`scripts/coverage-resume.php`, `Makefile` `release-parallel`) keeps the failed run's
Clover and JUnit reports, reruns the failed and changed test files when only `tests/` changed (a hash of every other
input is unchanged), and merges Clover reports line by line with the higher count. Clover has no per-test data, so it
cannot tell which tests a source change affects nor drop what a rerun test no longer covers.

## Design decisions (and where Beacon's proposal was changed)

1. **Per-test data from `--coverage-php`**, read through php-code-coverage's own objects (up to 11: a `CodeCoverage`
   object, lines list test ids; 12+: a serialized array, lines map test indexes to hit counts). The merged result is
   written back by the library (`Report\PHP` / `Report\Clover`, or `Report\Facade`), so the report equals a full run's.
2. **Merge drops per test, not only per file.** Beacon proposed dropping the old data of the changed files. That leaves
   stale lines in *unchanged* files: if `Other::thrice()` (changed) stops calling `Calc::triple()` (unchanged), the old
   data still says `triple()` is covered. The merge therefore removes every contribution of the rerun (and deleted)
   test classes on every file, then adds the new run. Proven by `PerTestCoverageTest` and the end-to-end test.
3. **Body-only changes.** A changed source file resumes only when its tokens outside function bodies are unchanged
   (`PhpSkeleton`). Then a behaviour change can only come from executing the changed bodies, and every test that
   called them executed a line of the file. This covers constants, property defaults, attributes, doc comments
   (PropertyInfo / annotations), imports, inheritance, trait use and generator-ness, which per-test line data cannot
   see. A changed body that had no executable line in the kept run also means the full suite (nobody is recorded as
   calling it).
4. **Changes by content, not by commit.** Beacon proposed "the kept run's base commit must be an ancestor of HEAD and
   not older than its parent". The release flow puts a fix *below* the release commit and re-commits the release, so
   the kept commit is no longer an ancestor: that rule would refuse the main use case, and it ignores uncommitted
   changes. The kept run stores the content hash of every input file; any difference is classified. The ancestry rule
   stays available as an opt-in history check in `coverage-resume.mk` (`COVERAGE_RESUME_MAX_COMMITS`).
5. **Marker policy by green resumes.** "A resume never replaces the last full green run for more than one commit" is
   implemented as: once a resumed run *passed* since the last full run (`--max-green-resumes=1`), the next run is the
   full suite; failed resumes may chain (to fix a failed run step by step) up to `--max-resumes=5`.
6. **Hierarchy and names, as defence in depth.** Tests of subclasses, implementations and trait users of a changed
   class, and test files naming a changed class (data providers run before coverage starts), are rerun too.

## Requirements

- **FR-001**: `plan` exits 0 with the test files to rerun, or 3 with the reasons for the full suite; it records the
  decision and the content snapshot in `<state>/pending.json` and the stamp in `<state>/stamp.txt`.
- **FR-002**: full suite when: forced (`--force-full`, `FORCE_COVERAGE`, `COVERAGE_RESUME=0`); no kept run or another
  format / root; another PHP, PHPUnit or php-code-coverage version; a green resume since the last full run, or
  `--max-resumes` chained; a changed file outside the source and test directories, or a non-test file under the test
  directories; a source file added, deleted, renamed, not PHP, matching `--full-glob`, changed outside function
  bodies, or changing a body without an executable line; more than `--max-src-files` (10) source files; nothing to
  rerun. One fixture per rule in `ResumePlannerTest`.
- **FR-003**: `merge` refuses (exit 3) another driver or branch / path data; otherwise writes the merged
  `--coverage-php` and Clover files in the library's format.
- **FR-004**: `save` keeps nothing when files changed during the run; a full run resets the counters.
- **FR-005**: `coverage-resume.mk` runs the cycle as one release phase and stamps `coverage: full` or
  `coverage: resumed (k test files, m source files)`; `assert-release-proof` shows a `release-check coverage:` tag line.
- **FR-006**: 100 % line coverage; PHPStan level 8; Rector, CS Fixer clean; an end-to-end test on a sample project
  shows the merged Clover report equals a full run's.

## Audit QF (2026-11-15) — changes

- **QF-H1**: the tests' status is kept apart from the gate's; a kept run whose tests failed with no failing JUnit
  test case, or under a `failOn*` configuration (unless `--trust-junit-failures`), means the full suite; a JUnit
  test-class suite without test cases counts as failed.
- **QF-H2**: test files with coverage metadata are rerun on every source resume (their per-test data is filtered to
  the covered targets).
- **QF-M1**: the "nobody calls it" rule checks executed lines, not executable ones.
- **QF-M2**: the residual risk is documented case by case; setUpBeforeClass, DependsExternal / cross-file Depends and
  subprocess test files are rerun on every source resume; a resumed run is a pre-check, not a proof.
- **QF-L1 / L2 / L3, I1, I2**: stamp written only by `save` and bound to the content hashes; atomic writes, coverage
  hash in the state, per-project lock; `assert-release-proof --require-coverage=full`; counters validated, CI-cache
  warning; the stamp says "test files" and "source files".

## Audit QF-2 (2026-11-15) — changes

- **QF2-M1**: a kept run whose tests failed resumes only with `--trust-junit-failures`, whatever the PHPUnit
  configuration (a data provider error or a runner warning is not in the JUnit log either); the `failOn*`
  configuration rule and `--phpunit-config` are gone. End-to-end fixture s13c.
- **QF2-L1**: coverage metadata matched anywhere (grouped attributes) and through an aliased import (s9c).
- **QF2-L2**: `DependsExternal` / cross-file `Depends` consumers rerun on every resume, test-only included (s5b).
- **QF2-I1**: `Process::from…()`, `PhpProcess` and backticks count as subprocesses; helpers, traits and base classes
  are documented as not seen.
- **QF2-I2**: the `release-check` example fails on an unknown coverage stamp.

## Residual risk

Tests that were not rerun keep their old result and coverage; see USAGE → Residual risk for the reproduced cases
(static state and order, providers through a wrapper, `setUpBeforeClass()` in a base class, subprocesses through a
helper, cross-file dependencies through a base class, cached values). A resumed green run is therefore a pre-check:
the release proof should require a full run (`--require-coverage=full`).
