# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/php-quality-tools`  
**Last audited**: 2026-07-07

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. PHPUnit tests under `tests/` and shipped config templates under `config/` are out of this inventory scope unless promoted in the spec.

## Composer plugin entry (`src/`)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Plugin.php` | Composer plugin lifecycle | FR-PLUGIN-001, FR-PLUGIN-002, FR-PLUGIN-003, FR-PLUGIN-004, FR-PLUGIN-005 |

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
| PHP-CS-Fixer fixers | 3 | 3 |
| PHP-CS-Fixer set | 1 | 1 |
| Rector rules | 5 | 5 |
| Rector set | 1 | 1 |
| Placeholders & README | 4 | 4 |
| **Total `src/` artifacts** | **15** | **15** |
