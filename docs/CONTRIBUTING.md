# Contributing

Thank you for considering contributing to PHP Quality Tools!

## Maintainer

This project is maintained by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech).

## Development Setup

### Using Docker (Recommended)

1. Clone the repository:
   ```bash
   git clone https://github.com/nowo-tech/php-quality-tools.git
   cd php-quality-tools
   ```

2. Start the Docker container:
   ```bash
   make up
   ```

3. Install dependencies:
   ```bash
   make install
   ```

4. Run tests:
   ```bash
   make test
   ```

### Without Docker

1. Clone the repository:
   ```bash
   git clone https://github.com/nowo-tech/php-quality-tools.git
   cd php-quality-tools
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Run tests:
   ```bash
   composer test
   ```

## Pull Request Process

1. Fork the repository
2. Create a feature branch from `develop`:
   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b feature/amazing-feature
   ```
3. Make your changes
4. Run tests and code style checks:
   ```bash
   make qa
   # or without Docker:
   composer qa
   ```
5. Commit your changes following [Conventional Commits](https://www.conventionalcommits.org/):
   ```bash
   git commit -m 'feat(scope): add amazing feature'
   ```
6. Push to the branch:
   ```bash
   git push origin feature/amazing-feature
   ```
7. Open a Pull Request **to `develop`** (not `main`)

## Coding Standards

- Follow PSR-12 coding style
- Add tests for new features
- Update documentation as needed
- Keep commits atomic and descriptive
- Update docs/CHANGELOG.md with compatibility information

## Running Tests

### With Docker

```bash
# Run all tests
make test

# Run tests with coverage
make test-coverage

# Check code style
make cs-check

# Fix code style
make cs-fix

# Run all QA checks
make qa
```

### Without Docker

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

## Adding Framework Support

When adding support for a new framework:

1. Create framework directory: `config/{framework}/`
2. Add framework detection in `Plugin::FRAMEWORK_PACKAGES`
3. Create framework-specific config files
4. Add tests for framework detection
5. Update README.md with framework compatibility
6. Update docs/CHANGELOG.md with PHP and framework versions

## Version Compatibility

When releasing a new version, always document:

- **PHP versions** supported
- **Framework versions** supported (Symfony, Laravel, etc.)
- **Breaking changes** (if any)

Example in CHANGELOG:

```markdown
## [1.1.0] - 2024-12-15

### Compatibility
- **PHP**: >= 8.1
- **Symfony**: 6.0 - 7.4
- **Laravel**: 9.0 - 11.0
```

## Reporting Issues

When reporting issues, please include:
- PHP version
- Framework and version (Symfony, Laravel, etc.)
- Composer version
- Operating system
- Steps to reproduce
- Expected vs actual behavior

## Contact

For questions or suggestions, you can reach out to:
- GitHub: [@HecFranco](https://github.com/HecFranco)
- Organization: [nowo-tech](https://github.com/nowo-tech)

