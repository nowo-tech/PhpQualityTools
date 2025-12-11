# PHP Quality Tools

[![CI](https://github.com/nowo-tech/php-quality-tools/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/php-quality-tools/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/nowo-tech/php-quality-tools/v)](https://packagist.org/packages/nowo-tech/php-quality-tools)
[![License](https://poser.pugx.org/nowo-tech/php-quality-tools/license)](https://packagist.org/packages/nowo-tech/php-quality-tools)
[![PHP Version Require](https://poser.pugx.org/nowo-tech/php-quality-tools/require/php)](https://packagist.org/packages/nowo-tech/php-quality-tools)

Pre-configured quality tools for PHP projects. Includes ready-to-use configurations for:

- **Rector** - Automated code refactoring
- **PHP-CS-Fixer** - Code style fixing (PSR-12 + Symfony)
- **Twig-CS-Fixer** - Twig template style fixing

## Automatic Framework Detection

The package **automatically detects** your framework and installs the appropriate configuration:

| Framework | Detection | Rector | PHP-CS-Fixer | Twig-CS-Fixer |
|-----------|-----------|--------|--------------|---------------|
| **Symfony** | `symfony/framework-bundle` | ✅ Symfony-specific | ✅ | ✅ |
| **Laravel** | `laravel/framework` | ✅ Laravel-specific | ✅ | ❌ |
| **Yii** | `yiisoft/yii2` | ✅ Generic | ✅ | ❌ |
| **CakePHP** | `cakephp/cakephp` | ✅ Generic | ✅ | ❌ |
| **Laminas** | `laminas/laminas-mvc` | ✅ Generic | ✅ | ❌ |
| **CodeIgniter** | `codeigniter4/framework` | ✅ Generic | ✅ | ❌ |
| **Slim** | `slim/slim` | ✅ Generic | ✅ | ❌ |
| **Other** | - | ✅ Generic | ✅ | ✅ |

## Compatibility

| Version | PHP | Symfony | Laravel | Composer |
|---------|-----|---------|---------|----------|
| **1.0.0** | >= 8.1 | 6.0 - 7.4 | 9.0 - 11.0 | >= 2.0 |

### PHP Versions

- **PHP 8.1**: ✅ Fully supported
- **PHP 8.2**: ✅ Fully supported
- **PHP 8.3**: ✅ Fully supported
- **PHP 8.4**: ✅ Fully supported (when available)

### Symfony Versions

- **Symfony 6.0 - 6.4**: ✅ Supported
- **Symfony 7.0 - 7.4**: ✅ Supported

### Laravel Versions

- **Laravel 9.0**: ✅ Supported
- **Laravel 10.0**: ✅ Supported
- **Laravel 11.0**: ✅ Supported

## Features

- ✅ **Automatic framework detection** during installation
- ✅ **Automatic dependency installation** (Rector, PHP-CS-Fixer, etc.)
- ✅ Framework-specific configurations (Symfony, Laravel)
- ✅ Customizable without losing base settings
- ✅ Files are **NEVER overwritten** after first install
- ✅ Separate `*.custom.php` files for project-specific settings
- ✅ PHP 8.1+ support
- ✅ PSR-12 and Symfony coding standards

## Installation

```bash
composer require --dev nowo-tech/php-quality-tools
```

### Automatic Dependency Installation

During installation, the plugin will:

1. ✅ **Detect your framework** automatically
2. ✅ **Install configuration files** for your framework
3. ✅ **Check for suggested dependencies** (Rector, PHP-CS-Fixer, etc.)
4. ✅ **Ask if you want to install them** (in interactive mode)

**Example output:**

```
php-quality-tools: Detected framework: symfony
php-quality-tools: Installing rector.php
php-quality-tools: Installing rector.custom.php
php-quality-tools: Installing .php-cs-fixer.dist.php
php-quality-tools: Installing .php-cs-fixer.custom.php
php-quality-tools: Installing .twig-cs-fixer.php
php-quality-tools: Installing .twig-cs-fixer.custom.php
php-quality-tools: Installed 6 file(s) for symfony

php-quality-tools: Missing suggested dependencies detected:
  - rector/rector: Rector for automated code refactoring
  - rector/rector-symfony: Rector rules for Symfony
  - rector/rector-doctrine: Rector rules for Doctrine
  - rector/rector-phpunit: Rector rules for PHPUnit
  - friendsofphp/php-cs-fixer: PHP-CS-Fixer for code style fixing
  - vincentlanglet/twig-cs-fixer: Twig-CS-Fixer for Twig template style fixing

Would you like to install these dependencies now? (yes/no) [yes]: yes

php-quality-tools: Installing dependencies...
php-quality-tools: Dependencies installed successfully!
```

If you choose **yes**, dependencies will be installed automatically. If **no**, you can install them manually later.

### Manual Installation

If you prefer to install dependencies manually or in non-interactive mode:

```bash
# For Symfony projects
composer require --dev rector/rector rector/rector-symfony rector/rector-doctrine rector/rector-phpunit friendsofphp/php-cs-fixer vincentlanglet/twig-cs-fixer

# For Laravel projects
composer require --dev rector/rector driftingly/rector-laravel friendsofphp/php-cs-fixer

# For generic PHP projects
composer require --dev rector/rector friendsofphp/php-cs-fixer vincentlanglet/twig-cs-fixer
```

After installation, the following files are created in your project root:

| File | Purpose | Overwritten on update? |
|------|---------|------------------------|
| `rector.php` | Rector main config | ❌ Never |
| `rector.custom.php` | Your customizations | ❌ Never |
| `.php-cs-fixer.dist.php` | PHP-CS-Fixer main config | ❌ Never |
| `.php-cs-fixer.custom.php` | Your customizations | ❌ Never |
| `.twig-cs-fixer.php` | Twig-CS-Fixer main config | ❌ Never |
| `.twig-cs-fixer.custom.php` | Your customizations | ❌ Never |

## Quick Start

### Rector

```bash
# Preview changes
./vendor/bin/rector process --dry-run

# Apply changes
./vendor/bin/rector process
```

### PHP-CS-Fixer

```bash
# Preview changes
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Apply changes
./vendor/bin/php-cs-fixer fix
```

### Twig-CS-Fixer

```bash
# Check for issues
./vendor/bin/twig-cs-fixer lint templates/

# Fix issues
./vendor/bin/twig-cs-fixer lint --fix templates/
```

## Composer Scripts

Add these scripts to your `composer.json` for easier access:

```json
{
  "scripts": {
    "fix": "PHP_CS_FIXER_IGNORE_ENV=1 php-cs-fixer fix --config=.php-cs-fixer.dist.php --allow-risky=yes",
    "fix:check": "PHP_CS_FIXER_IGNORE_ENV=1 php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff --allow-risky=yes",
    "rector": "rector -c rector.php",
    "rector:process": "rector process -c rector.php",
    "twig:fix": "twig-cs-fixer fix --config=.twig-cs-fixer.dist.php",
    "twig:lint": "twig-cs-fixer lint --config=.twig-cs-fixer.dist.php",
    "twig:fix:check": "twig-cs-fixer lint --config=.twig-cs-fixer.dist.php --fix"
  }
}
```

Then you can run:

```bash
# PHP-CS-Fixer
composer fix          # Fix code style
composer fix:check    # Check code style (dry-run)

# Rector
composer rector           # Preview changes (dry-run)
composer rector:process   # Apply changes

# Twig-CS-Fixer
composer twig:lint        # Check Twig templates
composer twig:fix         # Fix Twig templates
composer twig:fix:check   # Check and fix Twig templates
```

**Note**: The `PHP_CS_FIXER_IGNORE_ENV=1` environment variable ensures PHP-CS-Fixer uses the config file even if environment variables are set.

## Customization

All customizations go in the `*.custom.php` files. These files are never overwritten.

### Rector Customization

Edit `rector.custom.php`:

```php
<?php
return [
    'paths' => [
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ],
    
    'skip' => [
        __DIR__ . '/src/Legacy/*',
        \Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector::class,
    ],
    
    'php_version' => 'php84',
    'symfony_version' => 74, // For Symfony projects
    
    'features' => [
        'type_declarations' => true,
        'dead_code' => true,
        'code_quality' => true,
        'early_return' => true,
        'naming' => true,
        'privatization' => true,
    ],
];
```

### PHP-CS-Fixer Customization

Edit `.php-cs-fixer.custom.php`:

```php
<?php
return [
    'paths' => [
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ],
    
    'exclude' => [
        'vendor',
        'var',
        'src/Legacy',
    ],
    
    'rules' => [
        // Override or add rules
        'concat_space' => ['spacing' => 'none'],
        'yoda_style' => true,
    ],
];
```

### Twig-CS-Fixer Customization

Edit `.twig-cs-fixer.custom.php`:

```php
<?php
return [
    'paths' => [
        __DIR__ . '/templates',
        __DIR__ . '/src/Resources/views',
    ],
    
    'exclude' => [
        'vendor',
    ],
    
    'disabled_rules' => [
        // Disable specific rules
    ],
];
```

## Required Dependencies

This package provides configurations and can **automatically install** the required tools during installation.

### Automatic Installation (Recommended)

The plugin will detect missing dependencies and offer to install them automatically. Just answer **yes** when prompted.

### Manual Installation

If you prefer to install manually or the automatic installation didn't work:

#### Symfony Projects

```bash
composer require --dev \
    rector/rector \
    rector/rector-symfony \
    rector/rector-doctrine \
    rector/rector-phpunit \
    friendsofphp/php-cs-fixer \
    vincentlanglet/twig-cs-fixer
```

#### Laravel Projects

```bash
composer require --dev \
    rector/rector \
    driftingly/rector-laravel \
    friendsofphp/php-cs-fixer
```

#### Generic PHP Projects

```bash
composer require --dev \
    rector/rector \
    friendsofphp/php-cs-fixer \
    vincentlanglet/twig-cs-fixer
```

## Makefile Integration

Add to your `Makefile`:

```makefile
.PHONY: cs cs-fix rector rector-fix lint

# Check code style
cs:
	./vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix code style
cs-fix:
	./vendor/bin/php-cs-fixer fix

# Preview Rector changes
rector:
	./vendor/bin/rector process --dry-run

# Apply Rector changes
rector-fix:
	./vendor/bin/rector process

# Check Twig templates
twig-lint:
	./vendor/bin/twig-cs-fixer lint templates/

# Fix Twig templates
twig-fix:
	./vendor/bin/twig-cs-fixer lint --fix templates/

# Run all checks
lint: cs rector twig-lint
```

## CI Integration

### GitHub Actions

```yaml
name: Code Quality

on: [push, pull_request]

jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      
      - run: composer install
      
      - name: PHP-CS-Fixer
        run: ./vendor/bin/php-cs-fixer fix --dry-run --diff
      
      - name: Rector
        run: ./vendor/bin/rector process --dry-run
```

## Updating

When you update this package:

```bash
composer update nowo-tech/php-quality-tools
```

- ✅ Your custom files (`*.custom.php`) are **preserved**
- ✅ Main config files are **NOT overwritten**
- ✅ Missing dependencies will be detected and offered for installation
- ℹ️ Check the [CHANGELOG](CHANGELOG.md) for new features and compatibility updates

To get the latest base configs, delete the main files and reinstall:

```bash
rm rector.php .php-cs-fixer.dist.php .twig-cs-fixer.php
composer reinstall nowo-tech/php-quality-tools
```

## Development

### Using Docker (Recommended)

```bash
# Start the container
make up

# Install dependencies
make install

# Run tests
make test

# Run all QA checks
make qa
```

### Without Docker

```bash
composer install
composer test
composer qa
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

For branching strategy, see [docs/BRANCHING.md](docs/BRANCHING.md).

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for version history and compatibility information.

## Author

Created by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech)

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
