# Feature Specification: ParaTest per-process isolation helpers

**Feature Branch**: `main`  
**Created**: 2026-10-16  
**Status**: Released in 1.5.0  
**Input**: Symfony Beacon spec `149-parallel-release-gate` FR-003: extract the `pid_path` env var processor (`App\Shared\Env\PidPathEnvVarProcessor`) and the stale-PID cleanup loops of `scripts/phpunit-reset.sh` into this package, and document the parallel coverage gate (`paratest --processes=N` + `check-coverage-threshold`).

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#parallel-phpunit-isolation-since-150), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-150)

---

## User Scenarios & Testing

### User Story 1 — One path per test process (Priority: P1)

As a maintainer running PHPUnit through ParaTest, I configure a test-only path as `%env(pid_path:default:app.test_dir:APP_TEST_DIR)%`, so every worker process writes its own marker files and no process deletes another one's state.

**Acceptance Scenarios**:

1. **Given** `APP_TEST_DIR=/dev/shm/markers/`, **When** resolved in process 12345, **Then** `/dev/shm/markers-12345`.
2. **Given** an empty or non-string value, **Then** a `RuntimeException` names the env var.
3. **Given** the variable is unset and a `default:` parameter, **Then** the parameter value is suffixed (resolved through a compiled container).

### User Story 2 — Clean only dead processes (Priority: P1)

As a maintainer, before a test run I remove the per-process paths left by finished runs without touching those of a run still in progress.

**Acceptance Scenarios**:

1. **Given** `/dev/shm/app-markers-{pid}` directories for a live and a dead PID, **When** `clean-stale-pid-paths '/dev/shm/app-markers-{pid}'`, **Then** only the dead one is removed (recursively), exit 0.
2. **Given** `'/dev/shm/app-phpunit-{pid}.db*'`, **Then** the database and its `-wal` / `-shm` / `-journal` siblings of dead PIDs are removed.
3. **Given** `--dry-run`, **Then** nothing is removed and the candidates are listed.
4. **Given** a pattern without exactly one `{pid}`, **Then** exit 2.

---

## Requirements

- **FR-PID-001**: `Testing\Env\PidPathEnvVarProcessor` implements Symfony `EnvVarProcessorInterface`, prefix `pid_path` (type `string`), returns `rtrim($path, '/') . '-' . getmypid()`, never mutates `$_ENV` / `putenv()`. Registered by the host (tag `container.env_var_processor`, `when@test`); the package registers nothing.
- **FR-PID-002**: `bin/clean-stale-pid-paths [--dry-run] [--quiet] <pattern>...` (logic in `Testing\Pid\StalePidPathsCommand`), registered in `composer.json` `bin`. Patterns: exactly one `{pid}` (`[0-9]+`, PID > 0), `*` / `?` wildcards within one path segment, other glob metacharacters literal.
- **FR-PID-003**: Liveness: `/proc/<pid>` when procfs is mounted, else `posix_kill($pid, 0)` (EPERM = alive); neither available → treated as alive (nothing removed).
- **FR-PID-004**: Removal: directories recursively, symlinks unlinked without following them. Exit 0 OK / nothing matched, 1 a path could not be removed (`ERROR:` on stderr), 2 usage error. Summary line unless `--quiet`.
- **FR-PID-005**: `docs/USAGE.md` documents registration, the `default:` fallback, the cleanup binary and a ParaTest coverage Makefile recipe (cache warm-up once, no pipes, `check-coverage-threshold`).

### Compatibility

- **FR-COMPAT-001**: Composer plugin and existing gates unchanged; no new `require`; MINOR release. `src/Testing/` stays excluded from the Igor audit (test-only code).

## Success Criteria

- **SC-001**: 100 % PHP line coverage (`make test-coverage`).
- **SC-002**: `make release-check` passes.
- **SC-003**: An integration test runs the real binary against the live PHPUnit PID and a dead PID.

## Out of scope

- A ParaTest wrapper binary: the documented recipe composes `paratest` and `check-coverage-threshold`.
- Adopting the helpers in Beacon (follow-up in the app).
