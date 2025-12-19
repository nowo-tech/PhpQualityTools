# PHP Quality Tools

[![CI](https://github.com/nowo-tech/PhpQualityTools/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/PhpQualityTools/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/nowo-tech/php-quality-tools/v)](https://packagist.org/packages/nowo-tech/php-quality-tools)
[![License](https://poser.pugx.org/nowo-tech/php-quality-tools/license)](https://packagist.org/packages/nowo-tech/php-quality-tools)
[![PHP Version Require](https://poser.pugx.org/nowo-tech/php-quality-tools/require/php)](https://packagist.org/packages/nowo-tech/php-quality-tools)
[![GitHub stars](https://img.shields.io/github/stars/nowo-tech/PhpQualityTools.svg?style=social&label=Star)](https://github.com/nowo-tech/PhpQualityTools)

> ⭐ **Found this project useful?** Give it a star on GitHub! It helps us maintain and improve the project.

Pre-configured quality tools for PHP projects. Includes ready-to-use configurations for:

- **Rector** - Automated code refactoring
- **PHP-CS-Fixer** - Code style fixing (PSR-12 + Symfony)
- **Twig-CS-Fixer** - Twig template style fixing

## Automatic Framework Detection

The package **automatically detects** your framework and installs the appropriate configuration:

| Framework | Detection | Rector | PHP-CS-Fixer | Template Engine | Template Formatter |
|-----------|-----------|--------|--------------|----------------|-------------------|
| **Symfony** | `symfony/framework-bundle` | ✅ Symfony-specific | ✅ | Twig (default) | ✅ Twig-CS-Fixer (if `twig/twig` installed) |
| **Laravel** | `laravel/framework` | ✅ Laravel-specific | ✅ (includes Blade) | Blade (default), Twig (optional) | ✅ PHP-CS-Fixer for Blade |
| **Yii** | `yiisoft/yii2` | ✅ Generic | ✅ | PHP native views, Twig (optional) | ✅ Twig-CS-Fixer (if `twig/twig` installed) |
| **CakePHP** | `cakephp/cakephp` | ✅ Generic | ✅ | PHP native (.ctp files), Twig (optional) | ✅ Twig-CS-Fixer (if `twig/twig` installed) |
| **Laminas** | `laminas/laminas-mvc` | ✅ Generic | ✅ | PHP native, Twig (optional), Smarty (optional) | ✅ Twig-CS-Fixer (if `twig/twig` installed) |
| **CodeIgniter** | `codeigniter4/framework` | ✅ Generic | ✅ | PHP native views | ❌ No formatter available |
| **Slim** | `slim/slim` | ✅ Generic | ✅ | PHP native, Twig (optional) | ✅ Twig-CS-Fixer (if `twig/twig` installed) |
| **Other** | - | ✅ Generic | ✅ | Varies (Twig, PHP native, etc.) | ✅ Twig-CS-Fixer (if `twig/twig` installed) |

**Note**: Template formatter configurations are only installed if the corresponding template engine is detected in your dependencies. For example, Twig-CS-Fixer config is only installed if `twig/twig` is present in your `composer.json`.

## Compatibility

| Version | PHP | Symfony | Laravel | Composer |
|---------|-----|---------|---------|----------|
| **1.0.0** | >= 8.1 | 6.0 - 7.4 | 9.0 - 11.0 | >= 2.0 |

### PHP Versions

- **PHP 8.1**: ✅ Fully supported
- **PHP 8.2**: ✅ Fully supported
- **PHP 8.3**: ✅ Fully supported
- **PHP 8.4**: ✅ Fully supported
- **PHP 8.5**: ✅ Fully supported

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
3. ✅ **Check for template engine dependencies** (e.g., Twig for Twig-CS-Fixer)
4. ✅ **Install template formatter configs only if dependencies are present**
5. ✅ **Check for suggested dependencies** (Rector, PHP-CS-Fixer, etc.)
6. ✅ **Ask if you want to install them** (in interactive mode)

**Example output:**

```
php-quality-tools: Detected framework: symfony
php-quality-tools: Installing rector.php
php-quality-tools: Installing rector.custom.php
php-quality-tools: Installing .php-cs-fixer.php
php-quality-tools: Installing .php-cs-fixer.custom.php
php-quality-tools: Installing .twig-cs-fixer.php
php-quality-tools: Installing .twig-cs-fixer.custom.php
php-quality-tools: Installed 6 file(s) for symfony
```

**Note**: If Twig is not installed, you'll see:
```
php-quality-tools: Twig not detected, skipping Twig-CS-Fixer configuration
```

php-quality-tools: Missing suggested dependencies detected:
  - rector/rector: Rector for automated code refactoring
  - rector/rector-symfony: Rector rules for Symfony (only for Rector 1.x)
  - rector/rector-doctrine: Rector rules for Doctrine (only for Rector 1.x)
  - rector/rector-phpunit: Rector rules for PHPUnit (only for Rector 1.x)
  - friendsofphp/php-cs-fixer: PHP-CS-Fixer for code style fixing
  - vincentlanglet/twig-cs-fixer: Twig-CS-Fixer for Twig template style fixing

**Note**: If you're using Rector 2.x, the optional packages (`rector-symfony`, `rector-doctrine`, `rector-phpunit`) will be automatically skipped as they are not compatible with Rector 2.x yet.

Would you like to install these dependencies now? (yes/no) [yes]: yes

php-quality-tools: Installing dependencies...
php-quality-tools: Dependencies installed successfully!
```

If you choose **yes**, dependencies will be installed automatically. If **no**, you can install them manually later.

### Manual Installation

If you prefer to install dependencies manually or in non-interactive mode:

**Note**: The plugin automatically detects your Rector version:
- **For Rector 1.x**: Optional packages are installed normally
- **For Rector 2.x**: Optional packages are automatically skipped (not compatible yet)

```bash
# For Symfony projects (Rector 1.x)
composer require --dev rector/rector rector/rector-symfony:^1.0 rector/rector-doctrine:^0.16 rector/rector-phpunit:^1.0 friendsofphp/php-cs-fixer vincentlanglet/twig-cs-fixer

# For Symfony projects (Rector 2.x)
# Note: Optional Rector packages are NOT compatible with Rector 2.x
composer require --dev rector/rector friendsofphp/php-cs-fixer vincentlanglet/twig-cs-fixer

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
| `.php-cs-fixer.php` | PHP-CS-Fixer main config | ❌ Never |
| `.php-cs-fixer.custom.php` | Your customizations | ❌ Never |
| `.twig-cs-fixer.php` | Twig-CS-Fixer main config (if `twig/twig` installed) | ❌ Never |
| `.twig-cs-fixer.custom.php` | Your customizations (if `twig/twig` installed) | ❌ Never |

**Template Engine Support:**

- **Twig**: Configuration installed automatically if `twig/twig` is detected (works with Symfony, Laravel, Yii, CakePHP, Laminas, Slim, Generic)
- **Blade (Laravel)**: Formatted via PHP-CS-Fixer (`.blade.php` files included automatically)
- **PHP Native Views** (Yii, CakePHP, CodeIgniter): Can be partially formatted with PHP-CS-Fixer, but template-specific syntax may need manual review
- **Smarty** (Laminas): No dedicated formatter available yet
- **CakePHP Templates (.ctp)**: No dedicated formatter available yet

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

### Template Formatters

#### Twig-CS-Fixer (All frameworks with Twig)

**Note**: Twig-CS-Fixer configuration is automatically installed if `twig/twig` is detected in your project dependencies, regardless of framework.

**Supported frameworks**: Symfony (default), Laravel (optional), Yii (optional), CakePHP (optional), Laminas (optional), Slim (optional), Generic

```bash
# Check for issues
./vendor/bin/twig-cs-fixer lint templates/

# Fix issues
./vendor/bin/twig-cs-fixer lint --fix templates/
```

#### Blade Templates (Laravel)

Laravel Blade templates (`.blade.php` files) are PHP files with special syntax and can be formatted using PHP-CS-Fixer. The Laravel configuration includes `.blade.php` files automatically.

```bash
# Format Blade templates (included in PHP-CS-Fixer)
./vendor/bin/php-cs-fixer fix resources/views --dry-run --diff
./vendor/bin/php-cs-fixer fix resources/views
```

**Note**: Some Blade-specific directives (like `@if`, `@foreach`, etc.) may need manual review after formatting, as PHP-CS-Fixer treats them as PHP code.

## Composer Scripts

**✨ Automatic Installation**: The plugin automatically adds Composer scripts to your `composer.json` during installation. You don't need to add them manually!

The following scripts are automatically added (if they don't already exist):

**All frameworks:**
- `cs-check` - Check code style (dry-run)
- `cs-fix` - Fix code style
- `rector` - Preview Rector changes (dry-run)
- `rector:fix` - Apply Rector changes
- `test` - Run PHPUnit tests (if phpunit is installed)

**If Twig is installed:**
- `twig-check` - Check Twig templates
- `twig-fix` - Fix Twig templates

**Laravel only:**
- `blade-check` - Check Blade templates (dry-run)
- `blade-fix` - Fix Blade templates

**Note**: Existing scripts in your `composer.json` are never overwritten. The plugin only adds missing scripts.

### Manual Scripts (Optional)

If you prefer different script names or commands, you can manually add them to your `composer.json`:

```json
{
  "scripts": {
    "fix": "PHP_CS_FIXER_IGNORE_ENV=1 php-cs-fixer fix --config=.php-cs-fixer.php --allow-risky=yes",
    "fix:check": "PHP_CS_FIXER_IGNORE_ENV=1 php-cs-fixer fix --config=.php-cs-fixer.php --dry-run --diff --allow-risky=yes",
    "rector": "rector -c rector.php",
    "rector:process": "rector process -c rector.php",
    "twig:fix": "twig-cs-fixer fix --config=.twig-cs-fixer.php",
    "twig:lint": "twig-cs-fixer lint --config=.twig-cs-fixer.php",
    "twig:fix:check": "twig-cs-fixer lint --config=.twig-cs-fixer.php --fix",
    "blade:fix": "php-cs-fixer fix resources/views --config=.php-cs-fixer.php",
    "blade:lint": "php-cs-fixer fix resources/views --config=.php-cs-fixer.php --dry-run --diff"
  }
}
```

### Using the Scripts

After installation, you can run the automatically installed scripts:

```bash
# PHP-CS-Fixer (automatically installed)
composer cs-check     # Check code style (dry-run)
composer cs-fix       # Fix code style

# Rector (automatically installed)
composer rector       # Preview changes (dry-run)
composer rector:fix   # Apply changes

# Template Formatters (if dependencies are installed)
# Twig-CS-Fixer (automatically installed if Twig is present)
composer twig-check   # Check Twig templates
composer twig-fix     # Fix Twig templates

# Blade Templates (Laravel only, automatically installed)
composer blade-check  # Check Blade templates (dry-run)
composer blade-fix    # Fix Blade templates

# Tests (automatically installed if PHPUnit is present)
composer test         # Run PHPUnit tests
```

**Note**: The scripts above are automatically added by the plugin. If you prefer different script names or commands, you can manually add them to your `composer.json` (see "Manual Scripts" section above).

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

# Template Formatters
# Check Twig templates
twig-lint:
	./vendor/bin/twig-cs-fixer lint templates/

# Fix Twig templates
twig-fix:
	./vendor/bin/twig-cs-fixer lint --fix templates/

# Check Blade templates (Laravel)
blade-lint:
	./vendor/bin/php-cs-fixer fix resources/views --dry-run --diff

# Fix Blade templates (Laravel)
blade-fix:
	./vendor/bin/php-cs-fixer fix resources/views

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
- ✅ **New config files are NOT created** during update (only on install)
- ✅ Missing dependencies will be detected and offered for installation
- ℹ️ Check the [CHANGELOG](docs/CHANGELOG.md) for new features and compatibility updates

**Important**: Configuration files are only created during `composer install`, not during `composer update`. This ensures that:
- Existing files are never overwritten
- New files are only added on fresh installations
- Your customizations are always preserved

To get the latest base configs, delete the main files and reinstall:

```bash
rm rector.php .php-cs-fixer.php .twig-cs-fixer.php
composer install
```

For detailed upgrade instructions, breaking changes, and version-specific notes, see the [UPGRADE Guide](docs/UPGRADE.md).

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

## Template Engines by Framework

Each framework uses different template engines. Here's a complete overview:

### Supported Template Formatters

| Framework | Default Template Engine | Optional Engines | Formatter Available |
|-----------|------------------------|------------------|---------------------|
| **Symfony** | Twig | - | ✅ Twig-CS-Fixer |
| **Laravel** | Blade | Twig (via twigbridge) | ✅ PHP-CS-Fixer (Blade), Twig-CS-Fixer (if Twig installed) |
| **Yii** | PHP native views | Twig | ✅ Twig-CS-Fixer (if Twig installed) |
| **CakePHP** | PHP native (.ctp) | Twig | ✅ Twig-CS-Fixer (if Twig installed) |
| **Laminas** | PHP native | Twig, Smarty | ✅ Twig-CS-Fixer (if Twig installed) |
| **CodeIgniter** | PHP native views | - | ❌ No formatter (PHP-CS-Fixer can format PHP code) |
| **Slim** | PHP native | Twig | ✅ Twig-CS-Fixer (if Twig installed) |

### Template Engines Without Dedicated Formatters

The following template engines are used but don't have dedicated formatters yet:

- **CakePHP Templates (.ctp)**: PHP native template files
  - Can be partially formatted with PHP-CS-Fixer
  - Template-specific syntax may need manual review

- **Yii Views**: PHP native template files
  - Can be partially formatted with PHP-CS-Fixer
  - Template-specific syntax may need manual review

- **CodeIgniter Views**: PHP native template files
  - Can be partially formatted with PHP-CS-Fixer
  - Template-specific syntax may need manual review

- **Smarty Templates (.tpl)**: Used by Laminas and other frameworks
  - No dedicated formatter available
  - Future: May support Smarty-CS-Fixer or similar tool
  - Package detection: `smarty/smarty`

**Note**: PHP native template files can be formatted using PHP-CS-Fixer, but template-specific directives and syntax may require manual review.

### Future Template Formatter Support

The following template formatters are planned for future releases:

- **Smarty-CS-Fixer**: For Smarty templates (`.tpl` files)
  - Will be automatically installed if `smarty/smarty` is detected
  - Framework support: Laminas, Generic

- **CakePHP Template Formatter**: For CakePHP template files (`.ctp`)
  - Will be automatically installed if `cakephp/cakephp` is detected
  - Framework support: CakePHP

- **Yii View Formatter**: For Yii PHP native views
  - Will be automatically installed if `yiisoft/yii2` is detected
  - Framework support: Yii

- **CodeIgniter View Formatter**: For CodeIgniter PHP native views
  - Will be automatically installed if `codeigniter4/framework` is detected
  - Framework support: CodeIgniter

**Contributing**: If you know of formatters for these template engines or would like to contribute support, please open an issue or pull request on GitHub.

## Custom Rules

PHP Quality Tools includes several custom rules for both Rector and PHP-CS-Fixer to improve code quality and formatting.

### Included Custom Rules

**Rector Rules:**
- `SplitLongGroupedImportsRector` - Formats long grouped imports multiline
- `SplitLongConstructorParametersRector` - Splits long constructor parameters multiline
- `AddMissingReturnTypeRector` - Adds missing return types to public/protected methods
- `SplitLongMethodCallRector` - Identifies long method call chains for multiline formatting

**PHP-CS-Fixer Fixers:**
- `MultilineGroupedImportsFixer` - Formats long grouped imports multiline
- `MultilineArrayFixer` - Formats long arrays multiline
- `ConsistentDocblockFixer` - Ensures consistent docblock formatting

See [docs/CUSTOM_RULES.md](docs/CUSTOM_RULES.md) for details on how to use these rules and create your own.

**Note**: Custom Rector rules require an additional dependency: `symplify/rule-doc-generator-contracts`. Install it with:
```bash
composer require --dev symplify/rule-doc-generator-contracts
```

If you use the custom rules without this dependency, you'll see an informative message indicating what's missing.

## Contributing

Please see [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) for details.

For branching strategy, see [docs/BRANCHING.md](docs/BRANCHING.md).

## Changelog

Please see [docs/CHANGELOG.md](docs/CHANGELOG.md) for version history and compatibility information.

## Upgrade Guide

For upgrade instructions, breaking changes, and troubleshooting, see [docs/UPGRADE.md](docs/UPGRADE.md).

## Author

Created by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech)

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
