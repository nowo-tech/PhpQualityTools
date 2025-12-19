# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.8] - 2024-12-19

### Added

- **Project custom fixers support**: PHP-CS-Fixer configuration now supports project-specific custom fixers
  - Add your own custom fixers via `project_custom_fixers` array in `.php-cs-fixer.custom.php`
  - Project fixers are registered in addition to PHP Quality Tools fixers
  - Allows extending functionality with project-specific code style rules
  - Example: `'project_custom_fixers' => [\App\Fixer\Custom\MyCustomFixer::class]`

### Improved

- **PHP-CS-Fixer configuration**: Enhanced custom configuration file
  - Added `cache_file` option in `.php-cs-fixer.custom.php` for custom cache location
  - Changed `line_ending` from hardcoded `"\n"` to `PHP_EOL` for better cross-platform compatibility
  - Improved code formatting and documentation in custom configuration files

### Fixed

- Fixed indentation issue in `.php-cs-fixer.custom.php` documentation comments

## [1.0.7] - 2024-12-19

### Changed

- **Composer scripts updated**: Improved script names and commands for better consistency
  - **PHP-CS-Fixer scripts**: Renamed from `cs-check`/`cs-fix` to `fix:check`/`fix`
    - Added `PHP_CS_FIXER_IGNORE_ENV=1` environment variable to ignore environment-specific config
    - Added `--allow-risky=yes` flag for risky rules
    - Scripts now reference `.php-cs-fixer.dist.php` configuration file
  - **Rector scripts**: Renamed from `rector:fix` to `rector:check` for clarity
    - `rector` now applies changes (removed dry-run)
    - `rector:check` is the dry-run version
    - Scripts now reference `rector.dist.php` configuration file
  - **Twig-CS-Fixer scripts**: Renamed and expanded script names
    - `twig-check` → `twig:lint` (dry-run linting)
    - `twig-fix` → `twig:fix` (apply fixes)
    - Added `twig:fix:check` (check with fix option)
    - All scripts now reference `.twig-cs-fixer.dist.php` configuration file
    - Better namespace-style naming consistency with colons (`:`) separator

### Improved

- Script naming follows a more consistent pattern with namespace-style separators (`:`)
- Scripts use `.dist.php` suffix to indicate distribution configuration files
- PHP-CS-Fixer scripts ignore environment-specific configurations for consistent behavior

## [1.0.6] - 2024-12-19

### Fixed

- **JSON format preservation**: Plugin now preserves the original indentation format when adding scripts to `composer.json`
  - Detects original indentation (2 spaces, 4 spaces, or tabs)
  - Maintains the original format instead of forcing 4-space indentation
  - Prevents unnecessary formatting changes to `composer.json`
- **Scripts order preservation**: Plugin now preserves the original order of existing scripts in `composer.json`
  - New scripts are added at the beginning of the scripts section (instead of being sorted alphabetically)
  - Existing scripts maintain their original order (no alphabetical reordering)
  - Respects your preferred script organization
- **Configuration files installation**: Configuration files are now copied during both `composer install` and `composer update`
  - Previously, files were only copied during `composer install`
  - Now files are created automatically on first installation, regardless of command used
  - Existing files are still never overwritten (preserves customizations)
- **Duplicate scripts prevention**: Added robust duplicate detection when adding scripts
  - Uses `array_key_exists()` instead of `isset()` for more reliable duplicate detection
  - Double-checks scripts before merging to prevent any duplicates
  - Ensures scripts are never added if they already exist in `composer.json`

### Added

- **JSON indentation detection**: New method `detectJsonIndentation()` to detect original JSON formatting
- **JSON formatting preservation**: New method `encodeJsonWithIndentation()` to maintain original formatting
- **Test coverage**: Added `JsonFormatPreservationTest.php` with comprehensive tests for JSON format preservation

### Improved

- Better user experience when installing/updating the package
- Configuration files are automatically available after installation or update

## [1.0.5] - 2024-12-19

### Fixed

- **Rector 2.x compatibility**: Fixed compatibility issues with optional Rector packages
  - Plugin now detects Rector version and handles optional packages accordingly
  - **For Rector 1.x**: Optional packages (`rector-symfony`, `rector-doctrine`, `rector-phpunit`) are installed normally
  - **For Rector 2.x**: Optional packages are automatically skipped (not compatible with Rector 2.x yet)
  - These packages (`rector-symfony:^1.0`, `rector-doctrine:^0.16`, `rector-phpunit:^1.0`) conflict with Rector 2.x
  - Fixes installation errors when Rector 2.x is installed

### Documentation

- Updated README.md with Rector 2.x compatibility information
- Updated UPGRADE.md with instructions for Rector 2.x
- Updated CHANGELOG.md with detailed Rector 2.x compatibility notes

## [1.0.4] - 2024-12-19

### Added

- **Test coverage for Rector version detection**: Added comprehensive tests for `getRectorVersion()` method
  - 5 new test cases covering all scenarios (Rector 1.x, 2.x, not installed, invalid version)
  - New test file: `RectorVersionDetectionTest.php`
  - Improved test coverage for Plugin class

### Improved

- **Dockerfile**: Enhanced development container setup
  - Added `curl` to system dependencies
  - Container now fully ready for dependency installation and test execution
  - All necessary tools pre-installed (PHP, Composer, PCOV, Git, etc.)

## [1.0.3] - 2024-12-19

### Fixed

- **PHP 8.1 compatibility**: Removed typed constants (`private const int`) which require PHP 8.3+
  - All constants now use untyped syntax compatible with PHP 8.1+
  - Fixed PHPUnit code coverage HTML generation error
- **PHP-CS-Fixer configuration conflict**: Removed conflicting `single_blank_line_before_namespace` rule
  - This rule conflicts with `blank_lines_before_namespace` included in `@PSR12`
  - Configuration now works correctly without conflicts
- **Rector package version compatibility**: Fixed compatibility issues with Rector 2.x
  - Plugin now detects Rector version and handles optional packages accordingly
  - **For Rector 1.x**: Optional packages (`rector-symfony`, `rector-doctrine`, `rector-phpunit`) are installed normally
  - **For Rector 2.x**: Optional packages are automatically skipped (not compatible yet)
  - These packages (`rector-symfony:^1.0`, `rector-doctrine:^0.16`, `rector-phpunit:^1.0`) conflict with Rector 2.x
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
