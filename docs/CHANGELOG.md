# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.3] - 2024-12-19

### Fixed

- **PHP 8.1 compatibility**: Removed typed constants (`private const int`) which require PHP 8.3+
  - All constants now use untyped syntax compatible with PHP 8.1+
  - Fixed PHPUnit code coverage HTML generation error
- **PHP-CS-Fixer configuration conflict**: Removed conflicting `single_blank_line_before_namespace` rule
  - This rule conflicts with `blank_lines_before_namespace` included in `@PSR12`
  - Configuration now works correctly without conflicts
- **Rector package version compatibility**: Added automatic Rector version detection for optional packages
  - Plugin now detects installed Rector version (1.x or 2.x)
  - Automatically uses compatible versions of optional Rector packages:
    - Rector 1.x: `rector-symfony:^1.0`, `rector-doctrine:^0.16`, `rector-phpunit:^1.0`
    - Rector 2.x: `rector-symfony:^2.0`, `rector-doctrine:^2.0`, `rector-phpunit:^2.0`
  - Fixes installation errors when Rector 2.x is installed

## [1.0.2] - 2024-12-18

### Changed

- **Configuration file naming**: Renamed `.php-cs-fixer.dist.php` to `.php-cs-fixer.php` for consistency with `rector.php` naming
  - All configuration files now follow the same naming pattern (without `.dist` suffix)
  - Updated all references in documentation, tests, and examples
- **Template formatter installation**: Template formatter configurations are now only installed if their corresponding template engine dependencies are detected
  - Twig-CS-Fixer config is only installed if `twig/twig` package is present
  - Improved detection logic to work across all frameworks (not just Symfony/Generic)
  - Added informative messages when template engines are not detected

### Added

- **Automatic Composer Scripts Installation**: The plugin now automatically adds Composer scripts to your `composer.json` during installation. Scripts are added based on the detected framework and installed dependencies:
  - `cs-check` and `cs-fix` for PHP-CS-Fixer (all frameworks)
  - `rector` and `rector:fix` for Rector (all frameworks)
  - `twig-check` and `twig-fix` for Twig-CS-Fixer (if Twig is installed)
  - `blade-check` and `blade-fix` for Laravel Blade templates
  - `test` for PHPUnit (if installed)
  - Existing scripts are never overwritten
  - Scripts are sorted alphabetically for better readability
- **Template engine documentation**: Comprehensive documentation of template engines used by each framework
  - Added "Template Engines by Framework" section in README
  - Documented all template engines (Twig, Blade, Smarty, PHP native views, etc.)
  - Listed template engines without dedicated formatters yet
  - Added future template formatter support roadmap
- **Execution order documentation**: Added clear documentation about the correct order to run quality tools
  - PHP-CS-Fixer should run first
  - Rector should run second
  - Template formatters should run last
  - Added explanation of why order matters

### Improved

- **Plugin template detection**: Twig-CS-Fixer is now available for all frameworks if Twig is installed
  - Previously only available for Symfony and Generic
  - Now works with Laravel, Yii, CakePHP, Laminas, Slim, and Generic
  - Better framework-agnostic template engine detection
- **Code comments**: Enhanced code comments in Plugin.php with detailed template engine information
  - Documented template engines by framework
  - Added instructions for adding future template formatters
  - Improved code maintainability

## [1.0.1] - 2024-12-XX

### Fixed

- Minor bug fixes and improvements

## [1.0.0] - 2024-12-11

### Compatibility

- **PHP**: >= 8.1 (tested with 8.1, 8.2, 8.3, 8.4, 8.5)
- **Symfony**: 6.0 - 7.4
- **Laravel**: 9.0 - 11.0
- **Composer**: >= 2.0

### Added

- **Automatic dependency installation**: Plugin detects and offers to install suggested dependencies (Rector, PHP-CS-Fixer, Twig-CS-Fixer)
  - Interactive prompts for missing dependencies during installation
  - Framework-specific dependency suggestions
  - Non-interactive mode shows manual installation commands
- **Automatic framework detection** during installation:
  - Symfony: `symfony/framework-bundle` or `symfony/symfony`
  - Laravel: `laravel/framework`
  - Yii: `yiisoft/yii2`
  - CakePHP: `cakephp/cakephp`
  - Laminas: `laminas/laminas-mvc`
  - CodeIgniter: `codeigniter4/framework`
  - Slim: `slim/slim`
  - Generic: Fallback for any PHP project
- **Framework-specific configurations**:
  - **Symfony**: Full Rector with SymfonySetList, Doctrine, PHPUnit, Twig-CS-Fixer
    - Supports Symfony 6.0 - 7.4
    - PHP 8.1 - 8.5
    - Suggested packages: `rector/rector`, `rector/rector-symfony`, `rector/rector-doctrine`, `rector/rector-phpunit`, `friendsofphp/php-cs-fixer`, `vincentlanglet/twig-cs-fixer`
  - **Laravel**: Rector with Laravel sets, PHP-CS-Fixer
    - Supports Laravel 9.0 - 11.0
    - PHP 8.1 - 8.5
    - Suggested packages: `rector/rector`, `driftingly/rector-laravel`, `friendsofphp/php-cs-fixer`
  - **Generic**: Works with any PHP project
    - PHP 8.1 - 8.5
    - Suggested packages: `rector/rector`, `friendsofphp/php-cs-fixer`, `vincentlanglet/twig-cs-fixer`
- Rector configuration with customizable settings
  - PHP 8.1 - 8.5 support
  - Doctrine, PHPUnit integration
  - Configurable feature flags
  - Framework-specific rule sets
- PHP-CS-Fixer configuration
  - PSR-12 + Symfony standards
  - Comprehensive rule set
  - Customizable paths and rules
- Twig-CS-Fixer configuration (Symfony and Generic only)
  - Standard Twig formatting rules
  - Customizable paths and disabled rules
- Composer plugin for automatic file installation
- Files are NEVER overwritten after first install
- Separate `*.custom.php` files for project-specific settings
- PHPUnit tests
- GitHub Actions CI/CD pipeline
- PHP-CS-Fixer configuration (PSR-12)
- Comprehensive documentation
- Docker development environment
- Makefile for common development tasks
- Git pre-commit hooks

### Notes

- Configuration files are only copied on first install
- Custom files (`*.custom.php`) are never overwritten
- Main config files are never overwritten after installation
- Dependencies are suggested but not required (can be installed manually)
- Framework detection is automatic based on `composer.json` dependencies
