# Feature Specification: Release guards (latest-tag deploy guard)

**Feature Branch**: `main`  
**Created**: 2026-10-14  
**Status**: Released in 1.3.0  
**Input**: Symfony Beacon `docs/ops/KIT-EXTRACTION.md` row #20 (release guards): extract the "Guard — deploy only the latest release tag" step of Beacon's `.github/workflows/deploy-on-tag.yml` (Beacon spec 136 FR-040, REL-TAG-1510) into a reusable binary, and document Beacon's `make release-check` as the recommended local release gate. nowo-tech-web carries an equivalent inline guard (`git ls-remote` + `skip=` output); HectorFrancoWeb, open-agendesk-v2 and sms-bridge have none.

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#release-guards-since-130), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-130)

---

## User Scenarios & Testing

### User Story 1 — Old tags never deploy (Priority: P1)

As a maintainer, I push (or backfill) a tag older than the newest release, and the tag-triggered deploy skips instead of rolling production back.

**Acceptance Scenarios**:

1. **Given** tags `v1.9.0`, `v1.10.0`, `v1.2.3`, **When** `assert-latest-tag v1.10.0`, **Then** exit 0 (numeric, not string order).
2. **Given** the same tags, **When** `assert-latest-tag v1.9.0`, **Then** exit 1 with `ERROR: v1.9.0 is not the latest release (v1.10.0).`
3. **Given** `--github-output` and `GITHUB_REF_NAME=v1.2.3`, **Then** `deploy=false` and `latest=v1.10.0` are appended to `$GITHUB_OUTPUT`, a `::notice` explains the skip, exit 0.
4. **Given** a pre-release tag `v2.0.0-rc1`, **Then** it is never the latest and does not outrank `v1.10.0`.

### User Story 2 — Guard without Composer (Priority: P2)

As a DevOps engineer, my deploy runner has no project `vendor/`; I clone the package at a pinned tag and run `php bin/assert-latest-tag --github-output` in a light guard job.

**Acceptance Scenarios**:

1. **Given** a bare clone (no `vendor/`), **When** the binary runs, **Then** `bin/autoload.php` loads `src/` directly and the guard works.

### User Story 3 — Local release gate (Priority: P2)

As a maintainer with hosted CI unavailable, I run `make release-check` (QA + coverage threshold + secrets scan) before tagging, following the documented recipe.

---

## Requirements

- **FR-REL-001**: `bin/assert-latest-tag [<tag>] [--prefix=v] [--stdin] [--fetch[=<remote>]] [--github-output]`, registered in `composer.json` `bin`; logic in `Gate\Release\LatestTagCommand`.
- **FR-REL-002**: Tag from the argument, else `GITHUB_REF_NAME`; a leading `refs/tags/` is stripped; none → exit 2.
- **FR-REL-003**: Release tags match `^<prefix>(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$` (prefix quoted, default `v`, may be empty; only `[A-Za-z0-9._/-]` allowed). Latest = numeric max of (major, minor, patch). The tag passes only when it equals the latest.
- **FR-REL-004**: Tag list from `git tag --list '<prefix>*'` in the working directory, after `git fetch --tags --force --quiet <remote>` when `--fetch` is given; or from stdin (`--stdin`, one per line, blank lines ignored). `--fetch` with `--stdin` is a usage error. A failing git command → exit 2 with its output.
- **FR-REL-005**: `--github-output` requires `GITHUB_OUTPUT`; appends `deploy=true|false` and `latest=<tag or empty>`; prints `::notice title=Deploy skipped::…` when false; exits 0 unless the file cannot be written (exit 2).
- **FR-REL-006**: Strict mode (no `--github-output`): exit 0 latest, 1 not latest (`ERROR:` on stderr). Always prints `pushed=<tag> latest=<latest|<none>>`.
- **FR-REL-007**: `bin/autoload.php` falls back to a PSR-4 loader for `src/` when no Composer autoloader exists.
- **FR-REL-008**: `docs/USAGE.md` documents the deploy workflow (guard job, single job, `git ls-remote` + `--stdin`) and the `make release-check` recipe (qa + `check-coverage-threshold` + Gitleaks).

### Compatibility

- **FR-COMPAT-001**: Composer plugin and existing gates unchanged; MINOR release. `bin/` and `src/Gate/` stay excluded from the Igor audit (one-shot CLI).

## Success Criteria

- **SC-001**: 100 % PHP line coverage including `LatestTagCommand` (`make test-coverage`).
- **SC-002**: `make release-check` passes.
- **SC-003**: Integration tests run the real binary against a temporary git repository and via stdin outside git.

## Out of scope

- Adopting the binary in Beacon, nowo-tech-web or other apps (follow-up in each app).
- A reusable GitHub composite action.
