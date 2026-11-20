<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Twig;

/**
 * `bin/check-twig-extra`: fail when a bundle ships Twig templates but `twig/extra-bundle` (and the demos) are not ready.
 *
 * - the package `composer.json` must name `twig/extra-bundle` and `twig/string-extra` (REQ-TWIG-004);
 * - every demo that boots TwigBundle (`demo/<name>/config/bundles.php`, also one level deeper) must name
 *   `twig/extra-bundle` in its `composer.json` and register `TwigExtraBundle` in `config/bundles.php`;
 * - `--forbid-raw=<dir>` also fails on the Twig `|raw` filter outside Twig comments in the templates under `<dir>`.
 *
 * A bundle without any `*.twig` file is not concerned.
 *
 * Exit codes: 0 OK, 1 violations, 2 usage error or a `--forbid-raw` directory that does not exist.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class TwigExtraCommand
{
    public const USAGE = <<<'TXT'
        Usage: check-twig-extra [--root=<dir>] [--forbid-raw=<dir>]...

        Fails when Twig applies (a *.twig file under src/, templates/ or Resources/) but twig/extra-bundle is not ready:
        the package composer.json must name twig/extra-bundle and twig/string-extra, and every demo that boots
        TwigBundle must name twig/extra-bundle in its composer.json and register TwigExtraBundle in config/bundles.php.
          --root=<dir>         bundle root (default: current directory)
          --forbid-raw=<dir>   also fail on the |raw filter outside Twig comments under <dir>, repeatable
                               (e.g. src/Resources/views, where HTML is printed through a sanitising filter)
        Exit codes: 0 OK, 1 violations, 2 usage error or a --forbid-raw directory that does not exist.
        TXT;

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private readonly string $cwd,
    ) {
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $root = $this->cwd;
        $forbidRaw = [];
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            [$name, $value] = str_contains($arg, '=') ? explode('=', $arg, 2) : [$arg, null];
            if (null === $value || '' === $value) {
                return $this->usageError("Unknown or empty option {$arg}");
            }
            if ('--root' === $name) {
                $root = str_starts_with($value, '/') ? $value : $this->cwd . '/' . $value;
            } elseif ('--forbid-raw' === $name) {
                $forbidRaw[] = trim((string) preg_replace('#^(\./)+#', '', $value), '/');
            } else {
                return $this->usageError("Unknown option {$arg}");
            }
        }
        $root = rtrim($root, '/');
        if (!is_dir($root)) {
            fwrite($this->stderr, "ERROR: root directory not found: {$root}\n");

            return 2;
        }

        $applies = false;
        foreach (['src', 'templates', 'Resources'] as $dir) {
            if ([] !== TemplateFiles::in($root, $dir)) {
                $applies = true;
                break;
            }
        }
        if (!$applies) {
            fwrite($this->stdout, "check-twig-extra: Twig does not apply (no templates) - OK\n");

            return 0;
        }

        $errors = [];
        $composer = is_file($root . '/composer.json') ? (string) file_get_contents($root . '/composer.json') : '';
        if (!str_contains($composer, '"twig/extra-bundle"')) {
            $errors[] = 'ERROR: twig/extra-bundle missing from package composer.json require (REQ-TWIG-004)';
        }
        if (!str_contains($composer, '"twig/string-extra"')) {
            $errors[] = 'ERROR: twig/string-extra missing from package composer.json require (REQ-TWIG-004 baseline)';
        }

        $demoManifests = [...(glob($root . '/demo/*/composer.json') ?: []), ...(glob($root . '/demo/*/*/composer.json') ?: [])];
        sort($demoManifests);
        foreach ($demoManifests as $demoManifest) {
            $bundlesFile = \dirname($demoManifest) . '/config/bundles.php';
            // A demo that never boots TwigBundle never renders Twig.
            if (!is_file($bundlesFile) || !str_contains((string) file_get_contents($bundlesFile), 'TwigBundle')) {
                continue;
            }
            if (!str_contains((string) file_get_contents($demoManifest), '"twig/extra-bundle"')) {
                $errors[] = 'ERROR: ' . substr($demoManifest, \strlen($root) + 1) . ' missing twig/extra-bundle (REQ-TWIG-004)';
            }
            $bundles = (string) file_get_contents($bundlesFile);
            if (!str_contains($bundles, 'Twig\\\\Extra\\\\TwigExtraBundle\\\\TwigExtraBundle') && !str_contains($bundles, 'Twig\\Extra\\TwigExtraBundle\\TwigExtraBundle')) {
                $errors[] = 'ERROR: ' . substr($bundlesFile, \strlen($root) + 1) . ' missing TwigExtraBundle (REQ-TWIG-004)';
            }
        }

        foreach ($forbidRaw as $dir) {
            if (!is_dir($root . '/' . $dir)) {
                fwrite($this->stderr, "ERROR: directory not found: {$dir} (under {$root})\n");

                return 2;
            }
            foreach (TemplateFiles::in($root, $dir) as $file) {
                $source = (string) preg_replace('/\{#.*?#\}/s', '', (string) file_get_contents($root . '/' . $file));
                if (1 === preg_match('/\|\s*raw\b/', $source)) {
                    $errors[] = "ERROR: |raw in {$file} (print HTML through a sanitising filter instead)";
                }
            }
        }

        if ([] !== $errors) {
            fwrite($this->stderr, implode("\n", $errors) . "\n");

            return 1;
        }
        fwrite($this->stdout, "check-twig-extra: OK\n");

        return 0;
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "ERROR: {$message}\n" . self::USAGE . "\n");

        return 2;
    }
}
