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

The plugin can add quality scripts (if missing), such as:

- `fix`, `fix:check`
- `rector`, `rector:check`
- `twig:lint`, `twig:fix` (when Twig is installed)
- `test` (when PHPUnit is installed)

Existing scripts are preserved.
