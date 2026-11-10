# Maintainer: Héctor Franco Aceituno (@HecFranco)
# Organization: nowo-tech (https://github.com/nowo-tech)

# Feature Specification: Layer rules per context in `check-module-boundaries`

**Feature Branch**: `feat/module-boundaries-layers`  
**Created**: 2026-11-10  
**Status**: Planned for 1.12.0  
**Input**: Symfony Beacon spec 225 (incremental migration to DDD), step 0 / T002: "`check-module-boundaries`: declare
contexts and layers; layer rules enforced for migrated contexts, reported (warn) for the rest; a baseline file lists
today's violations and may only shrink". Owner OK 2026-11-10.

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#layer-rules-per-context-since-1120), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#unreleased)

---

## User Scenarios & Testing

### User Story 1 — Layer rules for a migrated context (Priority: P1)

As a maintainer, a migrated context (`enforce`) fails CI when its Domain uses Symfony, Doctrine ORM / DBAL, or the UI /
Infrastructure layer of any context, when its UI uses a repository or the entity manager, or when code outside
Application / Infrastructure calls `flush()`.

**Acceptance Scenarios**:

1. **Given** `src/Uptime/Domain/Monitor.php` with `use Doctrine\ORM\Mapping as ORM;` and Uptime in `enforce`, **Then**
   exit 1, `error: src/Uptime/Domain/Monitor.php:5 references Doctrine\ORM\Mapping (Uptime Domain, layer rule domain-framework-free): …`.
2. **Given** `use Doctrine\Common\Collections\ArrayCollection;` in the Domain, **Then** no finding (default `domain_allow`).
3. **Given** `\App\Billing\UI\Controller\X::class`, a grouped `use Doctrine\ORM\{EntityManagerInterface as Em};`, or
   `Infrastructure\Foo::class` inside `namespace App\Uptime;`, **Then** each is found; the same names in a comment or a
   string are not.
4. **Given** `$this->em->flush()` in `src/Uptime/Legacy/` (outside every layer), **Then** `flush-in-application` fails.

### User Story 2 — Not yet migrated contexts are reported (Priority: P1)

As a maintainer, contexts in `warn` print their findings as `warning:` lines and the run passes; contexts not declared
in `layers.contexts` are not scanned; a map without `layers` prints exactly what 1.11.0 printed.

### User Story 3 — A baseline that only shrinks (Priority: P1)

**Acceptance Scenarios**:

1. **Given** no baseline, **When** `--update-baseline`, **Then** the file is created with every finding (enforce and warn).
2. **Given** a finding listed in the baseline, **Then** it does not fail and is counted as baselined.
3. **Given** a listed finding that no longer occurs, **Then** `warning: baseline entry no longer occurs …`; with
   `--strict-baseline`, exit 1.
4. **Given** a new finding, **When** `--update-baseline`, **Then** exit 1 listing it, the file unchanged; with
   `--allow-grow` it is added.
5. **Given** a baseline path outside the root or a symbolic link, **Then** `--update-baseline` refuses (exit 2).

### User Story 4 — Configurable and extensible rules (Priority: P2)

Layers and their directories / namespaces are configurable globally and per context; rules are a list of
`forbid_layers`, `allow_layers`, `forbid_names` and `forbid_calls` entries, with `'@default'` expanding to the default set.

## Requirements

- **FR-001**: `layers` is optional; without it, output and exit codes are unchanged (test `ModuleBoundaryLayersCommandTest::testMapsWithoutLayersBehaveAsBefore`).
- **FR-002**: names are read from tokens (`use`, fully-qualified, qualified names resolved against the namespace and
  imported aliases); comments and strings are never scanned.
- **FR-003**: config errors (unknown keys, layers, contexts, duplicate rule ids, bad modes) exit 2 with the key path.
- **FR-004**: baseline keys are `rule|file|subject` (no line numbers), a multiset; `--update-baseline` never grows an
  existing file without `--allow-grow`.
- **FR-005**: 100 % line coverage of the new classes; PHPStan level 8; Rector, CS Fixer, Igor clean.
