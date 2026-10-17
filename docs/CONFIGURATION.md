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
- `vendor/bin/assert-latest-tag` (since 1.3.0): tag argument or `GITHUB_REF_NAME`, `--prefix` (default `v`), `GITHUB_OUTPUT` with `--github-output`; tags from `git tag --list` (or `--stdin`).
- `Testing\QueryBudgetTrait`: register `Testing\Dbal\QueryCountingMiddleware` (tag `doctrine.middleware`) in the test environment.
- `Testing\Env\PidPathEnvVarProcessor` (since 1.5.0): register it with the tag `container.env_var_processor` under `when@test`, then use `%env(pid_path:…)%`.
- `vendor/bin/clean-stale-pid-paths` (since 1.5.0): one or more path patterns with a `{pid}` placeholder.
- `vendor/bin/check-no-inline-svg` (since 1.6.0): options only (`--templates`, `--scripts`, `--template-ext`, `--script-ext`, `--allow`, `--no-scripts`); defaults `templates/` (`.twig`) and `assets/` (`.ts`).
- `vendor/bin/check-asset-budgets` (since 1.6.0): `config/quality/asset-budgets.yaml` (or `.yml` / `.json` / `.php`, or `--config`) with `manifest`, `build_dir` and `budgets: { <entry>: { js|css|js_gzip|css_gzip: <size> } }`, and/or `--budget=<entry>:<kind>=<size>`.
- `vendor/bin/check-doc-links` (since 1.6.0): options only (`--root`, `--exclude`, `--check-anchors`, `--no-git`).
- `resources/make/php-worker-reset.mk` (since 1.6.0): Makefile variables `PHP_WORKER_RESET_COMPOSE`, `PHP_WORKER_RESET_EXTRA_COMPOSE`, `PHP_WORKER_RESET_SERVICE`, `PHP_WORKER_RESET_CACHE_CLEAR`, `PHP_WORKER_RESET_TIMEOUT`.

Full reference: [Usage → Gate scripts](USAGE.md#gate-scripts-since-120), [Usage → Release guards](USAGE.md#release-guards-since-130), [Usage → Query budgets](USAGE.md#query-budgets-in-phpunit-since-120), [Usage → Parallel PHPUnit isolation](USAGE.md#parallel-phpunit-isolation-since-150) and [Usage → Front-end and docs gates](USAGE.md#front-end-and-docs-gates-since-160).
