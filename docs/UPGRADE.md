# Upgrade Guide

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
  - Scripts reference `.php-cs-fixer.dist.php` configuration file
- **Rector scripts**: Improved naming for clarity
  - `rector` now applies changes (was `rector:fix`)
  - `rector:check` is the new dry-run version (was just `rector`)
  - Scripts reference `.rector.dist.php` configuration file
- **Twig-CS-Fixer scripts**: Renamed for consistency and expanded
  - `twig-check` → `twig:lint` (lint templates, dry-run)
  - `twig-fix` → `twig:fix` (fix templates)
  - Added `twig:fix:check` (check and fix templates)
  - All scripts reference `.twig-cs-fixer.dist.php` configuration file

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

4. **Automatic Composer Scripts**: The plugin now automatically adds Composer scripts to your `composer.json` during installation
   - Scripts are added automatically: `cs-check`, `cs-fix`, `rector`, `rector:fix`
   - Twig scripts (`twig-check`, `twig-fix`) are added if Twig is installed
   - Laravel Blade scripts (`blade-check`, `blade-fix`) are added for Laravel projects
   - Test script (`test`) is added if PHPUnit is installed
   - Existing scripts are never overwritten
   - You can now use `composer cs-fix`, `composer rector:fix`, etc. immediately after installation

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

- **Symfony 6.0 - 7.4** are supported
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

