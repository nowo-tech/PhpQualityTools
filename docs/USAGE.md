# Usage

## Run quality tools

Use Composer scripts from your project (after enabling `extra.php-quality-tools.auto_add_scripts` or adding scripts manually — see [Configuration](CONFIGURATION.md)):

```bash
composer fix:check
composer fix
composer rector:check
composer rector
composer test
```

If Twig tooling is installed:

```bash
composer twig:lint
composer twig:fix
```

## Typical local flow

1. Run style checks and fixes (`fix:check` / `fix`).
2. Run Rector in dry run (`rector:check`).
3. Run tests (`test`).
4. Apply Rector (`rector`) and re-run tests.

## Docker development (this repository)

In this repository, you can use:

```bash
make up
make cs-check
make phpstan
make test
```
