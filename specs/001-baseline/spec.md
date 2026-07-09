# Feature Specification: PhpQualityTools baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-07  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## User Scenarios & Testing

### User Story 1 — Auto-install quality configs on Composer update (Priority: P1)

As a PHP developer, I require `nowo-tech/php-quality-tools` and run `composer install` or `composer update`, so Rector, PHP-CS-Fixer, and (when Twig is present) Twig-CS-Fixer configs are copied into my project without overwriting existing files.

**Independent Test**: Fresh project with the plugin → post-install copies from `config/{framework}/` based on detected framework; re-run does not overwrite customized files.

**Acceptance Scenarios**:

1. **Given** Symfony is detected in `composer.json`, **When** post-install runs, **Then** Symfony-flavoured configs are copied from `config/symfony/` (fallback `config/generic/`).
2. **Given** `.php-cs-fixer.php` already exists, **When** post-install runs, **Then** the file is preserved.
3. **Given** `twig/twig` is not installed, **When** post-install runs, **Then** Twig-CS-Fixer config is skipped.

---

### User Story 2 — Apply Nowo custom PHP-CS-Fixer rules (Priority: P1)

As a maintainer, I import `CustomFixersSet` in my `.php-cs-fixer.php`, so docblocks, grouped imports, and long arrays are formatted consistently.

**Acceptance Scenarios**:

1. **Given** a file with misaligned multiline docblock, **When** `NowoTech/consistent_docblock` runs, **Then** lines are trimmed and aligned with ` * ` prefix.
2. **Given** a grouped import longer than 120 chars or with ≥3 items, **When** `NowoTech/multiline_grouped_imports` runs, **Then** imports break to multiline.
3. **Given** an inline array exceeding thresholds, **When** `NowoTech/multiline_array` runs, **Then** elements are one per line.

---

### User Story 3 — Apply Nowo custom Rector rules (Priority: P2)

As a maintainer, I enable rules from `CustomRulesSet`, so long lines are flagged for CS-Fixer follow-up and missing return types are inferred on public/protected methods.

**Acceptance Scenarios**:

1. **Given** a method chain ≥3 calls and >120 chars, **When** `SplitLongMethodCallRector` runs, **Then** the node is marked for multiline formatting (CS-Fixer completes layout).
2. **Given** a public method with only `return 'foo'`, **When** `AddMissingReturnTypeRector` runs, **Then** `: string` is added.
3. **Given** `RemoveUnusedUseStatementsRector`, **When** listed in `CustomRulesSet::getRules()`, **Then** it is **not** included in the default active set (stub only).

---

### User Story 4 — Optional Composer script injection (Priority: P3)

As an integrator, I set `extra.php-quality-tools.auto_add_scripts` to `true`, so standard QA scripts are appended to `composer.json` without manual editing.

---

## Requirements

### Composer plugin

- **FR-PLUGIN-001**: `Plugin` MUST subscribe to `POST_INSTALL_CMD` and `POST_UPDATE_CMD`.
- **FR-PLUGIN-002**: Framework detection MUST inspect `composer.json` require/require-dev for Symfony, Laravel, Yii, CakePHP, Laminas, CodeIgniter, Slim; default to `generic`.
- **FR-PLUGIN-003**: Config copy MUST be non-destructive (skip existing target files).
- **FR-PLUGIN-004**: Suggested packages (Rector 1.x/2.x, CS-Fixer, Twig-CS-Fixer) MAY be offered interactively on install.
- **FR-PLUGIN-005**: Script injection MUST only run when `extra.php-quality-tools.auto_add_scripts === true`.

### PHP-CS-Fixer

- **FR-CSFIXER-001**: Custom fixers MUST register names `NowoTech/consistent_docblock`, `NowoTech/multiline_grouped_imports`, `NowoTech/multiline_array` with documented length/count thresholds (120 chars, ≥3 items where applicable).
- **FR-CSFIXER-002**: `CustomFixersSet` MUST expose `getFixers()`, `getRules()`, and `hasFixers()` for integrator configs.

### Rector

- **FR-RECTOR-001**: Format-marker rules (`SplitLongGroupedImportsRector`, `SplitLongMethodCallRector`, `SplitLongConstructorParametersRector`) MUST use 120-char threshold and delegate final layout to PHP-CS-Fixer where documented.
- **FR-RECTOR-002**: `AddMissingReturnTypeRector` MUST infer scalar/`void`/`array` from return statements; `RemoveUnusedUseStatementsRector` remains a customizable stub excluded from the default set.
- **FR-RECTOR-003**: `CustomRulesSet` MUST list four active rules, validate optional `symplify/rule-doc-generator-contracts`, and expose dependency helpers.

### Maintainer docs

- **FR-DOC-001**: README files under `PhpCsFixer/Rules/` and `Rector/Rules/` document how to add project-specific fixers/rules; `.gitkeep` preserves empty rule directories in VCS.

---

## Success Criteria

- **SC-001**: **15/15** artifacts under `src/` mapped in [`code-inventory.md`](code-inventory.md).
- **SC-002**: Shipped templates under `config/` match behaviour described in [`docs/USAGE.md`](../../docs/USAGE.md).
- **SC-003**: PHPUnit and PHPStan pass in CI (`composer qa`).
- **SC-004**: No Packagist-visible behaviour change without spec + test updates.

---

## Out of scope

- Config templates under `config/` (documented in integrator docs, not counted in `src/` inventory).
- Demo trees unless promoted as stable API.
