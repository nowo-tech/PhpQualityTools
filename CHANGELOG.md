# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-12-11

### Compatibility

- **PHP**: >= 8.1 (tested with 8.1, 8.2, 8.3)
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
    - PHP 8.1 - 8.4
    - Suggested packages: `rector/rector`, `rector/rector-symfony`, `rector/rector-doctrine`, `rector/rector-phpunit`, `friendsofphp/php-cs-fixer`, `vincentlanglet/twig-cs-fixer`
  - **Laravel**: Rector with Laravel sets, PHP-CS-Fixer
    - Supports Laravel 9.0 - 11.0
    - PHP 8.0 - 8.4
    - Suggested packages: `rector/rector`, `driftingly/rector-laravel`, `friendsofphp/php-cs-fixer`
  - **Generic**: Works with any PHP project
    - PHP 8.1 - 8.4
    - Suggested packages: `rector/rector`, `friendsofphp/php-cs-fixer`, `vincentlanglet/twig-cs-fixer`
- Rector configuration with customizable settings
  - PHP 7.4 - 8.4 support
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
