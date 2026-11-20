<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Twig;

/**
 * `bin/check-no-raw-html-form`: fail when bundle views or demo templates use raw `<form>` / `<input>` / `<textarea>` /
 * `<select>` markup instead of Symfony Forms (`form_start` / `form_row` / `form_widget` / `form_end`).
 *
 * The default is the strictest behaviour: no exception at all. Exceptions are opt-in:
 * `--allow-form-themes` exempts `src/Resources/views/{form,Form}/`, `--allow-themes` every file whose name contains
 * `theme`, `--allow-ignore-marker` a control on the line below `{# twig-005-ignore: <reason> #}` (the reason is
 * mandatory), `--allow=<path-or-glob>` single files or globs (see the usage text).
 *
 * Exit codes: 0 OK, 1 raw markup found, 2 usage error, a directory that cannot be read, or nothing to scan (no `*.twig` file in the scanned directories).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class NoRawHtmlFormCommand
{
    public const USAGE = <<<'TXT'
        Usage: check-no-raw-html-form [--root=<dir>] [--dir=<dir>]... [--allow=<path>]...
                                      [--allow-form-themes] [--allow-themes] [--allow-ignore-marker]

        Fails on raw <form> / <input> / <textarea> / <select> in Twig (render forms through Symfony Form:
        form_start / form_row / form_widget / form_end). Strict by default: no exception.
          --root=<dir>           project root; every other path is relative to it (default: current directory)
          --dir=<dir>            directory to scan, repeatable (default: src/Resources/views and every demo/**/templates)
          --allow=<path|glob>    file (or glob, * crosses /: demo/*/templates/form/*) that may keep raw markup, repeatable
          --allow-form-themes    exempt src/Resources/views/form/ and src/Resources/views/Form/ (form themes)
          --allow-themes         exempt every file whose name contains "theme"
          --allow-ignore-marker  exempt a control on the line below {# twig-005-ignore: <reason> #} (reason required)
        An --allow entry that no longer contains raw markup is reported as a warning.
        Exit codes: 0 OK, 1 raw markup found, 2 usage error, unreadable directory or no *.twig file in the scanned directories.
        TXT;

    private const PATTERN = '#<(form|input|textarea|select)(\s|>|$)#i';

    private const IGNORE_MARKER = '/twig-005-ignore:\s*[^\s#]/';

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
        $dirs = [];
        $allow = [];
        $formThemes = false;
        $themes = false;
        $marker = false;
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            [$name, $value] = str_contains($arg, '=') ? explode('=', $arg, 2) : [$arg, null];
            if ('--allow-form-themes' === $arg) {
                $formThemes = true;
            } elseif ('--allow-themes' === $arg) {
                $themes = true;
            } elseif ('--allow-ignore-marker' === $arg) {
                $marker = true;
            } elseif (null === $value || '' === $value) {
                return $this->usageError("Unknown or empty option {$arg}");
            } elseif ('--root' === $name) {
                $root = str_starts_with($value, '/') ? $value : $this->cwd . '/' . $value;
            } elseif ('--dir' === $name) {
                $dirs[] = $this->normalize($value);
            } elseif ('--allow' === $name) {
                $allow[] = $this->normalize($value);
            } else {
                return $this->usageError("Unknown option {$arg}");
            }
        }

        $root = rtrim($root, '/');
        if (!is_dir($root)) {
            fwrite($this->stderr, "ERROR: root directory not found: {$root}\n");

            return 2;
        }
        foreach ($dirs as $dir) {
            if (!is_dir($root . '/' . $dir)) {
                fwrite($this->stderr, "ERROR: directory not found: {$dir} (under {$root})\n");

                return 2;
            }
        }
        if ([] === $dirs) {
            $dirs = array_filter(['src/Resources/views'], static fn (string $dir): bool => is_dir($root . '/' . $dir));
            $dirs = [...$dirs, ...TemplateFiles::demoTemplateDirectories($root)];
        }

        $allowed = array_fill_keys($allow, false);
        $hits = [];
        $scanned = 0;
        foreach ($dirs as $dir) {
            try {
                $files = TemplateFiles::in($root, $dir);
            } catch (\RuntimeException $e) {
                fwrite($this->stderr, 'ERROR: ' . $e->getMessage() . "\n");

                return 2;
            }
            foreach ($files as $file) {
                ++$scanned;
                if (($formThemes && $this->isFormThemeDirectory($file)) || ($themes && str_contains(basename($file), 'theme'))) {
                    continue;
                }
                $lines = explode("\n", (string) file_get_contents($root . '/' . $file));
                foreach ($lines as $index => $line) {
                    if (1 !== preg_match(self::PATTERN, $line)) {
                        continue;
                    }
                    $allowedBy = $this->allowedBy($file, array_keys($allowed));
                    if (null !== $allowedBy) {
                        $allowed[$allowedBy] = true;
                        continue;
                    }
                    if ($marker && $index > 0 && 1 === preg_match(self::IGNORE_MARKER, $lines[$index - 1])) {
                        continue;
                    }
                    $hits[$file . ':' . ($index + 1) . ':' . $line] = true;
                }
            }
        }
        if (0 === $scanned) {
            fwrite($this->stderr, 'ERROR: no *.twig file in the scanned directories (' . ([] === $dirs ? 'src/Resources/views, demo/**/templates' : implode(', ', $dirs)) . "): nothing to check.\n");

            return 2;
        }
        foreach ($allowed as $path => $stillNeeded) {
            if (!$stillNeeded) {
                fwrite($this->stderr, "warning: --allow={$path} " . (is_file($root . '/' . $path) ? 'no longer contains raw form markup: remove it from the allowlist' : 'matches no file with raw form markup in the scanned directories') . "\n");
            }
        }

        if ([] !== $hits) {
            fwrite($this->stderr, "ERROR: raw <form> / <input> / <textarea> / <select> in Twig (REQ-TWIG-005):\n" . implode("\n", array_keys($hits)) . "\n");

            return 1;
        }
        fwrite($this->stdout, 'check-no-raw-html-form: OK (' . implode(' ', $dirs) . ")\n");

        return 0;
    }

    /**
     * The `--allow` entry that covers the file: the exact path or a glob (`*` also crosses `/`, so
     * `demo/*\/templates/form/*` covers a whole directory).
     *
     * @param list<int|string> $entries
     */
    private function allowedBy(string $file, array $entries): ?string
    {
        foreach ($entries as $entry) {
            $entry = (string) $entry;
            if ($entry === $file || fnmatch($entry, $file)) {
                return $entry;
            }
        }

        return null;
    }

    private function isFormThemeDirectory(string $file): bool
    {
        return str_starts_with($file, 'src/Resources/views/form/') || str_starts_with($file, 'src/Resources/views/Form/');
    }

    private function normalize(string $path): string
    {
        return trim((string) preg_replace('#^(\./)+#', '', $path), '/');
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "ERROR: {$message}\n" . self::USAGE . "\n");

        return 2;
    }
}
