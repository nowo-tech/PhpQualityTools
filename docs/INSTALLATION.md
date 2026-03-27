# Installation

## Requirements

- PHP `>= 8.1 < 8.6`
- Composer `>= 2.0`

## Install the package

```bash
composer require --dev nowo-tech/php-quality-tools
```

## What happens during install

The Composer plugin:

1. Detects the framework (Symfony/Laravel/Generic).
2. Copies base configuration files when they do not exist.
3. Optionally installs suggested tooling dependencies in interactive mode.

## Verify installation

After installation, verify these files exist in the project root:

- `.rector.php`
- `.php-cs-fixer.php`
- `.rector.custom.php`
- `.php-cs-fixer.custom.php`

If Twig is installed, you should also get:

- `.twig-cs-fixer.php`
- `.twig-cs-fixer.custom.php`
