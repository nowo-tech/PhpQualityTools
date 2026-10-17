# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/php-quality-tools`  
**Last audited**: 2026-10-17

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. PHPUnit tests under `tests/` and shipped config templates under `config/` are out of this inventory scope unless promoted in the spec.

## Composer plugin entry (`src/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Plugin.php` | Composer plugin lifecycle | FR-PLUGIN-001, FR-PLUGIN-002, FR-PLUGIN-003, FR-PLUGIN-004, FR-PLUGIN-005, FR-PLUGIN-006 |

## Process runner (`src/Process/`, `@internal`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Process/CommandRunnerInterface.php` | Command execution seam for dependency install | FR-PLUGIN-006 |
| `Process/ExecCommandRunner.php` | Default `exec()` runner | FR-PLUGIN-006 |

## PHP-CS-Fixer custom fixers (`src/PhpCsFixer/Rules/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `PhpCsFixer/Rules/ConsistentDocblockFixer.php` | Docblock normalization | FR-CSFIXER-001 |
| `PhpCsFixer/Rules/MultilineGroupedImportsFixer.php` | Grouped `use` multiline | FR-CSFIXER-001 |
| `PhpCsFixer/Rules/MultilineArrayFixer.php` | Array multiline | FR-CSFIXER-001 |

## PHP-CS-Fixer set registry (`src/PhpCsFixer/Set/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `PhpCsFixer/Set/CustomFixersSet.php` | Fixer registration API | FR-CSFIXER-002 |

## Rector custom rules (`src/Rector/Rules/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Rector/Rules/SplitLongGroupedImportsRector.php` | Long grouped imports marker | FR-RECTOR-001 |
| `Rector/Rules/SplitLongMethodCallRector.php` | Long method chain marker | FR-RECTOR-001 |
| `Rector/Rules/SplitLongConstructorParametersRector.php` | Long constructor signature marker | FR-RECTOR-001 |
| `Rector/Rules/AddMissingReturnTypeRector.php` | Return type inference | FR-RECTOR-002 |
| `Rector/Rules/RemoveUnusedUseStatementsRector.php` | Unused `use` stub (not in active set) | FR-RECTOR-002 |

## Rector set registry (`src/Rector/Set/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Rector/Set/CustomRulesSet.php` | Rule list + dependency checks | FR-RECTOR-003 |

## Maintainer placeholders & docs (`src/**`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `PhpCsFixer/Rules/.gitkeep` | Directory placeholder | FR-DOC-001 |
| `PhpCsFixer/Rules/README.md` | Fixer authoring guide | FR-DOC-001 |
| `Rector/Rules/.gitkeep` | Directory placeholder | FR-DOC-001 |
| `Rector/Rules/README.md` | Rector rule authoring guide | FR-DOC-001 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Composer plugin (PHP) | 1 | 1 |
| Process runner (PHP) | 2 | 2 |
| PHP-CS-Fixer fixers | 3 | 3 |
| PHP-CS-Fixer set | 1 | 1 |
| Rector rules | 5 | 5 |
| Rector set | 1 | 1 |
| Placeholders & README | 4 | 4 |
| **Total `src/` artifacts** | **17** | **17** |

## Gate scripts and query budgets (`src/Gate/`, `src/Testing/`, since 1.2.0)

Specified in [`specs/002-gate-scripts/spec.md`](../002-gate-scripts/spec.md).

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Gate/Coverage/CloverReport.php` | Clover statements / lines | FR-GATE-COV-001 |
| `Gate/Coverage/CloverException.php` | Report errors → exit codes | FR-GATE-COV-004 |
| `Gate/Coverage/CoverageThresholdCommand.php` | `bin/check-coverage-threshold` | FR-GATE-COV-002, FR-GATE-COV-003, FR-GATE-COV-004 |
| `Gate/ModuleBoundary/ModuleBoundaryConfig.php` | Config loading / validation | FR-GATE-MB-001, FR-GATE-MB-002 |
| `Gate/ModuleBoundary/ConfigException.php` | Config errors (exit 2) | FR-GATE-MB-002 |
| `Gate/ModuleBoundary/ForbiddenEdge.php` | Deny-list edge | FR-GATE-MB-002 |
| `Gate/ModuleBoundary/Rule.php` | Extra rule types | FR-GATE-MB-005 |
| `Gate/ModuleBoundary/ImportExtractor.php` | Token-based imports | FR-GATE-MB-004 |
| `Gate/ModuleBoundary/ModuleBoundaryChecker.php` | Edge + rule evaluation | FR-GATE-MB-003, FR-GATE-MB-005, FR-GATE-MB-006 |
| `Gate/ModuleBoundary/Violation.php` | Violation value | FR-GATE-MB-007 |
| `Gate/ModuleBoundary/ModuleBoundaryCommand.php` | `bin/check-module-boundaries` | FR-GATE-MB-001, FR-GATE-MB-007 |
| `Testing/QueryCounter.php` | Recorded SQL | FR-QB-001 |
| `Testing/QueryBudgetTrait.php` | PHPUnit assertions | FR-QB-002, FR-QB-003 |
| `Testing/Dbal/QueryCountingMiddleware.php` | DBAL 4 middleware | FR-QB-001 |
| `Testing/Dbal/QueryCountingDriver.php` | Driver wrapper (`@internal`) | FR-QB-001 |
| `Testing/Dbal/QueryCountingConnection.php` | Connection wrapper (`@internal`) | FR-QB-001 |
| `Testing/Dbal/QueryCountingStatement.php` | Statement wrapper (`@internal`) | FR-QB-001 |

## Release guards (`src/Gate/Release/`, since 1.3.0)

Specified in [`specs/003-release-guards/spec.md`](../003-release-guards/spec.md).

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Gate/Release/LatestTagCommand.php` | `bin/assert-latest-tag` | FR-REL-001 – FR-REL-006 |
| `Gate/Release/ReleaseProofCommand.php` | `bin/assert-release-proof` (1.4.0) | none (CHANGELOG 1.4.0, docs/USAGE.md) |

## ParaTest isolation (`src/Testing/`, since 1.5.0)

Specified in [`specs/004-paratest-isolation/spec.md`](../004-paratest-isolation/spec.md).

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Testing/Env/PidPathEnvVarProcessor.php` | `pid_path` env var processor | FR-PID-001 |
| `Testing/Pid/StalePidPathsCommand.php` | `bin/clean-stale-pid-paths` | FR-PID-002 – FR-PID-004 |

## Front-end and docs gates (`src/Gate/Svg/`, `src/Gate/Assets/`, `src/Gate/DocLinks/`, `resources/make/`, since 1.6.0)

Specified in [`specs/005-frontend-docs-gates/spec.md`](../005-frontend-docs-gates/spec.md).

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Gate/Svg/NoInlineSvgCommand.php` | `bin/check-no-inline-svg` | FR-SVG-001, FR-SVG-002, FR-SVG-003 |
| `Gate/Assets/AssetBudgetCommand.php` | `bin/check-asset-budgets` | FR-AB-001, FR-AB-003 |
| `Gate/Assets/AssetBudgetConfig.php` | Budgets config / sizes | FR-AB-001 |
| `Gate/Assets/AssetBudgetException.php` | Errors → exit codes 1 / 2 | FR-AB-003 |
| `Gate/Assets/ViteManifest.php` | Entry + static imports | FR-AB-002 |
| `Gate/DocLinks/DocLinksCommand.php` | `bin/check-doc-links` | FR-DL-001, FR-DL-002, FR-DL-004 |
| `Gate/DocLinks/MarkdownAnchors.php` | GitHub heading slugs / HTML anchors | FR-DL-003 |
| `../resources/make/php-worker-reset.mk` | Makefile snippet | FR-MK-001 |
