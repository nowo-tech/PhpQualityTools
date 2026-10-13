# Configuration

## Base files

The package installs base configuration files in the project root:

- `.rector.php`
- `.php-cs-fixer.php`
- `.twig-cs-fixer.php` (only when Twig is detected)

These files are considered managed templates and are only created when missing.

## Project customizations

Customize behavior in:

- `.rector.custom.php`
- `.php-cs-fixer.custom.php`
- `.twig-cs-fixer.custom.php` (if Twig is used)

Custom files are never overwritten by the plugin.

## Composer scripts

Script injection is **opt-in**. Add to your project `composer.json`:

```json
{
  "extra": {
    "php-quality-tools": {
      "auto_add_scripts": true
    }
  }
}
```

When enabled, the plugin can add quality scripts (if missing), such as:

- `fix`, `fix:check`
- `rector`, `rector:check`
- `twig:lint`, `twig:fix` (when Twig is installed)
- `test` (when PHPUnit is installed)

Existing scripts are preserved.

## Gate scripts and query budgets (since 1.2.0)

Nothing to configure for the Composer plugin. The optional CI gates read their own inputs:

- `vendor/bin/check-coverage-threshold`: Clover path + minimum (argument, `--min` or `COVERAGE_MIN`).
- `vendor/bin/check-module-boundaries`: `config/quality/module-boundaries.yaml` (or `.yml` / `.php`, or `--config`).
- `Testing\QueryBudgetTrait`: register `Testing\Dbal\QueryCountingMiddleware` (tag `doctrine.middleware`) in the test environment.

Full reference: [Usage → Gate scripts](USAGE.md#gate-scripts-since-120) and [Usage → Query budgets](USAGE.md#query-budgets-in-phpunit-since-120).
