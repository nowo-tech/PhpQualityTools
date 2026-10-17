# Upgrade Guide

## Table of contents

- [Unreleased](#unreleased)
- [To 1.6.0](#to-160)
- [To 1.5.0](#to-150)
- [To 1.4.0](#to-140)
- [To 1.3.0](#to-130)
- [To 1.2.0](#to-120)
- [To 1.1.0](#to-110)
- [To 1.0.21](#to-1021)
- [To 1.0.20](#to-1020)
- [To 1.0.19](#to-1019)
- [To 1.0.18](#to-1018)
- [General Upgrade Process](#general-upgrade-process)
  - [1. Update the Package](#1-update-the-package)
  - [2. Review Configuration Files](#2-review-configuration-files)
  - [3. Get Latest Base Configurations (Optional)](#3-get-latest-base-configurations-optional)
  - [4. Check for Missing Dependencies](#4-check-for-missing-dependencies)
  - [5. Review Changelog](#5-review-changelog)
- [Version-Specific Upgrade Notes](#version-specific-upgrade-notes)
  - [Upgrading to 1.0.16](#upgrading-to-1016)
  - [Upgrading to 1.0.15](#upgrading-to-1015)
  - [Upgrading to 1.0.14](#upgrading-to-1014)
  - [Upgrading to 1.0.13](#upgrading-to-1013)
  - [Upgrading to 1.0.12](#upgrading-to-1012)
  - [Upgrading to 1.0.11](#upgrading-to-1011)
  - [Upgrading to 1.0.10](#upgrading-to-1010)
  - [Upgrading to 1.0.9](#upgrading-to-109)
  - [Upgrading to 1.0.8](#upgrading-to-108)
  - [Upgrading to 1.0.7](#upgrading-to-107)
  - [Upgrading to 1.0.6](#upgrading-to-106)
  - [Upgrading to 1.0.4](#upgrading-to-104)
  - [Upgrading to 1.0.3](#upgrading-to-103)
  - [Upgrading from Pre-Release to Latest](#upgrading-from-pre-release-to-latest)
  - [Upgrading to 1.0.0](#upgrading-to-100)
- [Breaking Changes](#breaking-changes)
  - [PHP Version Requirements](#php-version-requirements)
  - [Composer Version](#composer-version)
- [Framework-Specific Considerations](#framework-specific-considerations)
  - [Symfony Projects](#symfony-projects)
  - [Laravel Projects](#laravel-projects)
  - [Generic PHP Projects](#generic-php-projects)
- [Troubleshooting](#troubleshooting)
  - [Configuration Files Not Updated](#configuration-files-not-updated)
  - [Missing Dependencies](#missing-dependencies)
  - [Framework Detection Issues](#framework-detection-issues)
- [Best Practices](#best-practices)
- [Getting Help](#getting-help)
- [Related Documentation](#related-documentation)

## Unreleased

## To 1.6.0

From **1.5.0** — opt-in front-end and docs gates.

```bash
composer update nowo-tech/php-quality-tools
```

- No breaking changes; nothing runs unless you call the new binaries or include the Makefile snippet.
- **Own SVG guard test** (e.g. Symfony Beacon's `NoInlineSvgTemplatesTest`): replace it with `vendor/bin/check-no-inline-svg --allow=<sprite template>` in `make qa` / `release-check`, or keep a thin PHPUnit test that runs the command and asserts exit 0. Allow entries are paths relative to the project root (`templates/…`), not to `templates/`.
- **`scripts/check-asset-budgets.mjs`**: move the `BUDGETS` map to `config/quality/asset-budgets.yaml` (`budgets: { assets/app.ts: { js: 200kB, css: 250kB } }`; the script's `200_000` bytes are `200kB`) and call `vendor/bin/check-asset-budgets` after the build. Same `OK  ` / `FAIL` lines; a summary line is added; exit 2 (not 1) on configuration errors.
- **`scripts/check-doc-links.mjs`**: call `vendor/bin/check-doc-links`. Same file set (`git ls-files --cached --others --exclude-standard`) and `OK: doc links (…)` line. Differences: `/path` targets are now checked against the repository root (the script skipped them), and links with nested brackets (`[[1.0] - date](#x)`, `[![badge](img)](url)`) are parsed. Add `--check-anchors` once the `#fragments` are clean.
- **Own `php-worker-reset` target**: `-include vendor/nowo-tech/php-quality-tools/resources/make/php-worker-reset.mk` after setting `PHP_WORKER_RESET_COMPOSE` (and `PHP_WORKER_RESET_EXTRA_COMPOSE` for an E2E stack); remove the local target. See [Usage → Front-end and docs gates](USAGE.md#front-end-and-docs-gates-since-160).

## To 1.5.0

From **1.4.0** — opt-in ParaTest isolation helpers.

```bash
composer update nowo-tech/php-quality-tools
```

- No breaking changes; nothing runs or registers itself.
- **Own `pid_path` processor:** an application that ships its own `pid_path` env var processor (e.g. Symfony Beacon's `App\Shared\Env\PidPathEnvVarProcessor`) can delete it (and its test) and register `NowoTech\PhpQualityTools\Testing\Env\PidPathEnvVarProcessor` with the `container.env_var_processor` tag under `when@test`. Same prefix, same output; keep only one of them registered (with two, Symfony silently uses the last one registered for the prefix). `%env(pid_path:…)%` usages stay unchanged.
- **Stale per-process paths:** replace shell loops that delete per-PID files / directories of dead processes with `vendor/bin/clean-stale-pid-paths --quiet '<path-with-{pid}>' …`. See [Usage → Parallel PHPUnit isolation](USAGE.md#parallel-phpunit-isolation-since-150).

## To 1.4.0

## To 1.3.0

From **1.2.0** — opt-in deploy guard.

```bash
composer update nowo-tech/php-quality-tools
```

- No breaking changes; nothing runs unless you call the new binary.
- **Deploy guard:** replace an inline "deploy only the latest tag" shell step (`git tag --list 'v*' | grep -E … | sort -V | tail -n 1`) with `vendor/bin/assert-latest-tag --github-output` (same `deploy=true|false` output; keep `if: steps.<id>.outputs.deploy == 'true'` on the deploy steps). On a runner without the project's `vendor/`, use the guard-job example in [Usage → Release guards](USAGE.md#release-guards-since-130) (bare clone of the package, no Composer needed).
- Workflows using another output name (e.g. `skip=true|false`): switch the condition to `deploy != 'true'` / `deploy == 'true'`.

## To 1.2.0

From **1.1.0** — opt-in gate scripts and query-budget trait.

```bash
composer update nowo-tech/php-quality-tools
```

- No breaking changes; the Composer plugin behaves as before. Nothing runs unless you call the new tools.
- **Coverage gate:** replace a custom Clover script with `vendor/bin/check-coverage-threshold <clover.xml>` (same `COVERAGE_MIN` variable, same exit codes 0 / 1 / 2). It checks statements **and** lines by default; add `--metric=statements` to keep a statements-only gate.
- **Module boundaries:** move your rules to `config/quality/module-boundaries.yaml` and call `vendor/bin/check-module-boundaries`. YAML needs `symfony/yaml` (`composer require --dev symfony/yaml`), or use a `.php` file returning the same array.
- **Query budgets:** `composer require --dev doctrine/dbal:^4` if not installed, register `Testing\Dbal\QueryCountingMiddleware` with the `doctrine.middleware` tag in the test environment and `use QueryBudgetTrait` (implement `getQueryCounter()`). A test class that already defines its own `assertQueryBudget()` / `countQueries()` must rename them or drop them for the trait's. See [Usage](USAGE.md#query-budgets-in-phpunit-since-120).

## To 1.1.0

From **1.0.21** — no Rector 1.x add-on suggestions by default; read-only twig:fix:check.

```bash
composer update nowo-tech/php-quality-tools
```

- **`twig:fix:check`:** projects where the plugin already added this script keep the old command (existing scripts are never overwritten). Change it to `twig-cs-fixer lint --config=.twig-cs-fixer.php` in your `composer.json` so a "check" does not rewrite templates.
- **Rector add-ons:** if you installed `rector/rector-symfony` / `rector-doctrine` / `rector-phpunit` next to Rector 2.x because the plugin suggested them, you can remove them — Rector 2.x ships those rules.

## To 1.0.21

From **1.0.20** — test-suite isolation (no real `composer require` against the repo) and internal command-runner refactor.

```bash
composer update nowo-tech/php-quality-tools
```

- No breaking changes. No application upgrade steps. `Process\CommandRunnerInterface` / `ExecCommandRunner` are `@internal`; the plugin's behaviour and messages are unchanged.

## To 1.0.20

From **1.0.19** — dev-only lock/constraint sync.

```bash
composer update nowo-tech/php-quality-tools
```

- No breaking changes. No application upgrade steps.

## To 1.0.19

From **1.0.18** — PHPStan 2 docblock fix and dependency refresh.

```bash
composer update nowo-tech/php-quality-tools
```

- No breaking changes. No application upgrade steps.

## To 1.0.18

From **1.0.17** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/php-quality-tools
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

This guide helps you upgrade between versions of PHP Quality Tools.

## General Upgrade Process

### 1. Update the Package

```bash
composer update nowo-tech/php-quality-tools
```

### 2. Review Configuration Files

**Important**: Configuration files are **never overwritten** during updates. This means:

- ✅ Your customizations in `*.custom.php` files are **always preserved**
- ✅ Main config files (`.rector.php`, `.php-cs-fixer.php`, etc.) are **not modified**
- ✅ New configuration files are **automatically created** during updates if they don't exist (starting from version 1.0.6)

### 3. Get Latest Base Configurations (Optional)

If you want to update to the latest base configurations:

```bash
# Backup your custom files first
cp .rector.custom.php .rector.custom.php.backup
cp .php-cs-fixer.custom.php .php-cs-fixer.custom.php.backup

# Remove main config files (they will be recreated)
rm .rector.php .php-cs-fixer.php .twig-cs-fixer.php

# Reinstall to get latest base configs (works with both install and update)
composer install
# OR
composer update
```

**Note**: Your `*.custom.php` files will remain untouched.

**Note**: Starting from version 1.0.6, configuration files are copied during both `composer install` and `composer update`, so you can use either command.

### 4. Check for Missing Dependencies

The plugin will automatically detect and offer to install missing suggested dependencies during updates (in interactive mode).

If you're in non-interactive mode, install dependencies manually:

**Note**: The plugin automatically detects your Rector version and handles optional packages accordingly:

- **For Rector 1.x**: Optional packages (`rector-symfony`, `rector-doctrine`, `rector-phpunit`) are installed normally
- **For Rector 2.x**: Optional packages are automatically skipped (not compatible with Rector 2.x yet)

If you install manually:

```bash
# For Symfony projects (Rector 1.x)
composer require --dev rector/rector rector/rector-symfony:^1.0 rector/rector-doctrine:^0.16 rector/rector-phpunit:^1.0 friendsofphp/php-cs-fixer vincentlanglet/twig-cs-fixer

# For Symfony projects (Rector 2.x)
# Note: Optional Rector packages are NOT compatible with Rector 2.x
# The plugin will automatically skip them. Install only:
composer require --dev rector/rector friendsofphp/php-cs-fixer vincentlanglet/twig-cs-fixer

# For Laravel projects
composer require --dev rector/rector driftingly/rector-laravel friendsofphp/php-cs-fixer

# For generic PHP projects
composer require --dev rector/rector friendsofphp/php-cs-fixer vincentlanglet/twig-cs-fixer
```

### 5. Review Changelog

Always check [CHANGELOG.md](CHANGELOG.md) for:
- New features
- Breaking changes
- Compatibility updates
- Deprecations

## Version-Specific Upgrade Notes

### Upgrading to 1.0.16

**No action required** for typical consumers. CI runs `composer audit --locked`; dev dependency bumps only.

```bash
composer update nowo-tech/php-quality-tools
```

### Upgrading to 1.0.15

**No action required** for typical consumers — CS-only fixes on PhpCsFixer custom rules and a Rector coverage test import style. Continue requiring `nowo-tech/php-quality-tools: ^1.0`.

```bash
composer update nowo-tech/php-quality-tools
```

### Upgrading to 1.0.14

**No action required** for typical consumers — configs, scripts, and the `auto_add_scripts` opt-in contract are unchanged. Continue requiring `nowo-tech/php-quality-tools: ^1.0`.

**Contributors:** PHPStan now loads FrankenPHP classic + worker rulesets (`composer install` so `vendor/nowo-tech/phpstan-frankenphp` is present). `make down-dev` stops root compose. CI runs PHPStan in the `code-style` job.

### Upgrading to 1.0.13

**Plugin robustness (composer scripts opt-in):**
- Invalid or unreadable `composer.json` now yields an explicit error when the plugin tries to inject scripts (with `auto_add_scripts` enabled), instead of silently skipping.
- No change to the opt-in contract introduced in 1.0.12: set `extra.php-quality-tools.auto_add_scripts` to `true` only if you want automatic script keys.

**No action required** for typical consumers — existing configs and scripts are unchanged. Contributors should run `make setup-hooks` once per clone (REQ-GIT-001).

### Upgrading to 1.0.12

**Composer script injection (opt-in):**
- Starting in 1.0.12, the plugin **does not** add quality scripts to `composer.json` unless you opt in.
- Previously (1.0.0–1.0.11), missing scripts were added automatically on install/update.

**Action required (only if you want automatic scripts):**
- Add the following to your project `composer.json`, then run `composer update nowo-tech/php-quality-tools` (or `composer install`):

```json
{
  "extra": {
    "php-quality-tools": {
      "auto_add_scripts": true
    }
  }
}
```

- Existing scripts are still never overwritten. If you already have the scripts from an earlier version, **no action is required**.
- If you prefer manual control, copy the script definitions from [README.md](../README.md#manual-scripts-optional) instead of enabling `auto_add_scripts`.

**Symfony 8:**
- Symfony **8.0** and **8.1** are supported alongside 6.x and 7.x. No config changes are required for Symfony projects.

**No change** to config file names, `*.custom.php` customization workflow, or non-destructive file installation behavior.

### Upgrading to 1.0.11

**Composer scripts (automatically added by the plugin):**
- Script commands now pass `--config=` / `-c` to **`.php-cs-fixer.php`**, **`.rector.php`**, and **`.twig-cs-fixer.php`**, matching the filenames the plugin installs.
- Previously, generated scripts referenced `*.dist.php` files that are **not** created by the installer, which could make `composer fix` / `composer rector` fail on a fresh setup.

**Action required (only if you rely on the generated scripts):**
- The plugin **does not overwrite** existing `scripts` entries in your `composer.json`. If you still have old commands pointing to `*.dist.php`, update them to the filenames above, or remove those keys and let the plugin add the defaults on the next run (only for keys that are missing).

**No change** to config file names or to `*.custom.php` customization workflow.

**Contributors / CI only:** Tests were reorganized into `tests/Unit` and `tests/Integration` and PHP coverage was raised to 100%; this does not change how the Composer plugin behaves in your project. The root package `composer test` script was corrected so `composer test` runs PHPUnit (not a stray `color=always` path).

### Upgrading to 1.0.10

**Code quality and static analysis:**
- PHPStan level 8 compliance: type annotations and strict checks added across the codebase
- No configuration or API changes

**No action required** – Internal improvements only. Existing configurations and usage remain unchanged.

### Upgrading to 1.0.9

**Configuration File Naming Change:**
- **Rector configuration files renamed**: Changed from `rector.php` to `.rector.php`
  - Main config: `rector.php` → `.rector.php`
  - Custom config: `rector.custom.php` → `.rector.custom.php`
  - This maintains consistency with other configuration files (`.php-cs-fixer.php`, `.twig-cs-fixer.php`)

**Action Required:**
- If you have existing `rector.php` or `rector.custom.php` files, rename them:
  ```bash
  mv rector.php .rector.php
  mv rector.custom.php .rector.custom.php
  ```
- The plugin will automatically install the new `.rector.php` files on next install/update
- Your existing customizations in `rector.custom.php` should be moved to `.rector.custom.php`
- Update any scripts or CI/CD configurations that reference the old filenames

**Note**: This change ensures all configuration files follow a consistent naming convention with dot prefix.

### Upgrading to 1.0.8

**New Features:**
- **Project custom fixers support**: You can now add your own project-specific PHP-CS-Fixer fixers
  - Add fixers via `project_custom_fixers` array in `.php-cs-fixer.custom.php`
  - Project fixers are registered automatically alongside PHP Quality Tools fixers
  - Example: `'project_custom_fixers' => [\App\Fixer\Custom\MyCustomFixer::class]`

**Improvements:**
- **PHP-CS-Fixer configuration**: Enhanced custom configuration options
  - Added `cache_file` option for custom cache location
  - Changed `line_ending` to use `PHP_EOL` for better cross-platform compatibility

**No action required** - These are new features and improvements. Existing configurations continue to work.

### Upgrading to 1.0.7

**Script Names Changed:**
- **PHP-CS-Fixer scripts**: Renamed for consistency
  - `cs-check` → `fix:check` (check code style)
  - `cs-fix` → `fix` (fix code style)
  - Scripts now include `PHP_CS_FIXER_IGNORE_ENV=1` and `--allow-risky=yes` flags
  - Scripts were documented as referencing `.php-cs-fixer.dist.php`; installed configs use **`.php-cs-fixer.php`** (see **Upgrading to 1.0.11** for script command alignment).
- **Rector scripts**: Improved naming for clarity
  - `rector` now applies changes (was `rector:fix`)
  - `rector:check` is the new dry-run version (was just `rector`)
  - Scripts were documented as referencing `.rector.dist.php`; installed configs use **`.rector.php`** (see **Upgrading to 1.0.11**).
- **Twig-CS-Fixer scripts**: Renamed for consistency and expanded
  - `twig-check` → `twig:lint` (lint templates, dry-run)
  - `twig-fix` → `twig:fix` (fix templates)
  - Added `twig:fix:check` (check and fix templates)
  - Scripts were documented as referencing `.twig-cs-fixer.dist.php`; installed configs use **`.twig-cs-fixer.php`** (see **Upgrading to 1.0.11**).

**Action Required:**
- If you have custom scripts that reference the old script names, update them to use the new names
- If you're using the automatically installed scripts, they will be updated automatically on next install/update
- Existing scripts in your `composer.json` are never overwritten, so old scripts remain until you manually update them

**Note**: The old script names will continue to work if you haven't removed them, but we recommend updating to the new names for consistency.

### Upgrading to 1.0.6

**Bug Fixes:**
- **JSON format preservation**: The plugin now preserves your `composer.json` indentation format when adding scripts
  - If your `composer.json` uses 2 spaces, it will remain 2 spaces
  - If it uses 4 spaces, it will remain 4 spaces
  - If it uses tabs, it will remain tabs
  - No more forced formatting changes to your `composer.json`
- **Configuration files installation**: Configuration files are now copied during both `composer install` and `composer update`
  - Previously, new files were only created during `composer install`
  - Now files are created automatically regardless of which command you use
  - This fixes the issue where files weren't copied when using `composer update` or `composer require`

**No action required** - These are bug fixes that improve the installation experience.

### Upgrading to 1.0.4

**Test Coverage Improvements:**
- **Rector version detection tests**: Added comprehensive test coverage for `getRectorVersion()` method
  - 5 new test cases covering all scenarios (Rector 1.x, 2.x, not installed, invalid version)
  - Improved overall test coverage for Plugin class
  - New test file: `RectorVersionDetectionTest.php`

**Development Environment:**
- **Dockerfile enhancements**: Improved development container setup
  - Added `curl` to system dependencies
  - Container now fully ready for dependency installation and test execution
  - All necessary tools pre-installed

**No action required** - These are improvements to test coverage and development environment.

### Upgrading to 1.0.3

**Bug Fixes:**
- **PHP 8.1 compatibility**: Fixed typed constants that required PHP 8.3+
  - All constants now use untyped syntax compatible with PHP 8.1+
  - Fixes PHPUnit code coverage HTML generation errors
- **PHP-CS-Fixer configuration**: Removed conflicting `single_blank_line_before_namespace` rule
  - This rule conflicts with `blank_lines_before_namespace` included in `@PSR12`
  - Configuration now works correctly without conflicts
- **Rector package compatibility**: Added automatic Rector version detection
  - Plugin now detects installed Rector version (1.x or 2.x)
  - Automatically uses compatible versions of optional Rector packages
  - Fixes installation errors when Rector 2.x is installed

**No action required** - These are bug fixes that improve compatibility.

### Upgrading from Pre-Release to Latest

If you're upgrading from a version that used `.php-cs-fixer.dist.php`:

1. **Configuration File Rename**: The file `.php-cs-fixer.dist.php` has been renamed to `.php-cs-fixer.php`
   - If you have an old `.php-cs-fixer.dist.php` file, you can safely delete it
   - The new `.php-cs-fixer.php` will be created on next install
   - Update any scripts or references that use the old filename
   - Composer scripts in README have been updated with the new filename

2. **Template Formatter Detection**: Template formatter configs are now only installed if dependencies are detected
   - If you had Twig-CS-Fixer config but don't have `twig/twig` installed, it won't be installed automatically
   - Install `twig/twig` if you want Twig-CS-Fixer configuration
   - This prevents unnecessary config files

3. **Execution Order**: Follow the documented order when running quality tools
   - Run PHP-CS-Fixer first
   - Run Rector second
   - Run template formatters last

4. **Composer Scripts** (historical — see **Upgrading to 1.0.12** for current behavior): From 1.0.2 through 1.0.11, the plugin added quality scripts automatically when missing. Since **1.0.12**, script injection is **opt-in** via `extra.php-quality-tools.auto_add_scripts`.
   - Legacy script names evolved over time (`cs-check`/`cs-fix` → `fix:check`/`fix`, etc.)
   - Twig, Blade, and `test` scripts follow the same opt-in rule since 1.0.12
   - Existing scripts are never overwritten

### Upgrading to 1.0.0

This is the initial release. If you're upgrading from a pre-release version:

1. **PHP Version Requirement**: Ensure you're using PHP >= 8.1
2. **Composer Version**: Requires Composer >= 2.0
3. **Configuration Files**: All configuration files will be created on first install
4. **Dependencies**: Install suggested dependencies as needed

## Breaking Changes

### PHP Version Requirements

- **PHP 8.1+** is required starting from version 1.0.0
- If you're using PHP 8.0 or earlier, you'll need to upgrade PHP first

### Composer Version

- **Composer 2.0+** is required
- Upgrade Composer if you're using version 1.x:
  ```bash
  composer self-update
  ```

## Framework-Specific Considerations

### Symfony Projects

- **Symfony 6.0 - 8.1** are supported
- Ensure your Symfony version is compatible
- Twig-CS-Fixer is included for Symfony projects

### Laravel Projects

- **Laravel 9.0 - 11.0** are supported
- **Blade templates**: Formatted using PHP-CS-Fixer (`.blade.php` files included automatically)
- **Twig support**: Twig-CS-Fixer config will be installed if `twig/twig` is detected (optional, via twigbridge)
- Uses `driftingly/rector-laravel` for Laravel-specific Rector rules

### Generic PHP Projects

- Works with any PHP 8.1+ project
- **Twig support**: Twig-CS-Fixer config will be installed if `twig/twig` is detected
- Supports any template engine that has a dedicated formatter

## Troubleshooting

### Configuration Files Not Updated

If you need the latest base configurations:

1. **Backup your customizations**:
   ```bash
   cp .rector.custom.php .rector.custom.php.backup
   cp .php-cs-fixer.custom.php .php-cs-fixer.custom.php.backup
   ```

2. **Remove and reinstall**:
   ```bash
   rm .rector.php .php-cs-fixer.php .twig-cs-fixer.php
   composer install
   ```

3. **Restore your customizations** from the backup files

### Missing Dependencies

If you see errors about missing dependencies:

1. Check the [README.md](../README.md) for framework-specific dependencies
2. Install them manually:
   ```bash
   composer require --dev <package-name>
   ```

### Framework Detection Issues

If the wrong framework is detected:

1. Check your `composer.json` for framework packages
2. The plugin detects frameworks based on these packages:
   - Symfony: `symfony/framework-bundle` or `symfony/symfony`
   - Laravel: `laravel/framework`
   - Yii: `yiisoft/yii2`
   - CakePHP: `cakephp/cakephp`
   - Laminas: `laminas/laminas-mvc`
   - CodeIgniter: `codeigniter4/framework`
   - Slim: `slim/slim`
   - Generic: Fallback for any other project

## Best Practices

1. **Always backup** your `*.custom.php` files before major updates
2. **Review the changelog** before upgrading
3. **Test in a development environment** first
4. **Keep dependencies up to date** for best compatibility
5. **Use version constraints** in `composer.json`:
   ```json
   {
     "require-dev": {
       "nowo-tech/php-quality-tools": "^1.0"
     }
   }
   ```

## Getting Help

If you encounter issues during upgrade:

1. Check the [CHANGELOG.md](CHANGELOG.md) for known issues
2. Review the [README.md](../README.md) for usage instructions
3. Open an issue on [GitHub](https://github.com/nowo-tech/PhpQualityTools/issues)
4. Include:
   - PHP version
   - Framework and version
   - Composer version
   - Error messages
   - Steps to reproduce

## Related Documentation

- [README.md](../README.md) - Installation and usage
- [CHANGELOG.md](CHANGELOG.md) - Version history and changes
- [CONTRIBUTING.md](CONTRIBUTING.md) - Contributing guidelines
- [CUSTOM_RULES.md](CUSTOM_RULES.md) - Custom rules documentation

