<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Twig;

/**
 * `bin/check-no-raw-html-form`: fail when bundle views or demo templates use raw `<form>` / `<input>` / `<textarea>` /
 * `<select>` markup instead of Symfony Forms (`form_start` / `form_row` / `form_widget` / `form_end`).
 *
 * Exempt by default (documented exceptions): form themes (`src/Resources/views/form/` and `Form/`, and any
 * `*theme*.html.twig`), which implement the form blocks and emit the controls; a control that is never submitted when
 * the line above it carries `{# twig-005-ignore: <reason> #}`; the files named with `--allow`. `--strict` turns the
 * theme exemption off.
 *
 * Exit codes: 0 OK, 1 raw markup found, 2 usage error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class NoRawHtmlFormCommand
{
    public const USAGE = <<<'TXT'
        Usage: check-no-raw-html-form [--root=<dir>] [--dir=<dir>]... [--allow=<path>]... [--strict]

        Fails on raw <form> / <input> / <textarea> / <select> in Twig (render forms through Symfony Form:
        form_start / form_row / form_widget / form_end).
          --root=<dir>    project root; every other path is relative to it (default: current directory)
          --dir=<dir>     directory to scan, repeatable (default: src/Resources/views and every demo/**/templates)
          --allow=<path>  file that may keep raw markup (e.g. a deprecated fallback partial), repeatable
          --strict        no form-theme exemption (default: src/Resources/views/{form,Form}/ and *theme*.html.twig are exempt)
        A control that is never submitted is exempt when the line above it carries {# twig-005-ignore: <reason> #}.
        An --allow entry that no longer contains raw markup is reported as a warning.
        Exit codes: 0 OK, 1 raw markup found, 2 usage error.
        TXT;

    private const PATTERN = '#<(form|input|textarea|select)(\s|>|$)#i';

    private const IGNORE_MARKER = 'twig-005-ignore';

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
        $strict = false;
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            [$name, $value] = str_contains($arg, '=') ? explode('=', $arg, 2) : [$arg, null];
            if ('--strict' === $arg) {
                $strict = true;
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
        if ([] === $dirs) {
            fwrite($this->stdout, "check-no-raw-html-form: no Twig views - OK\n");

            return 0;
        }

        $allowed = array_fill_keys($allow, false);
        $hits = [];
        foreach ($dirs as $dir) {
            foreach (TemplateFiles::in($root, $dir) as $file) {
                if (!$strict && $this->isFormTheme($file)) {
                    continue;
                }
                $lines = explode("\n", (string) file_get_contents($root . '/' . $file));
                foreach ($lines as $index => $line) {
                    if (1 !== preg_match(self::PATTERN, $line)) {
                        continue;
                    }
                    if (isset($allowed[$file])) {
                        $allowed[$file] = true;
                        continue;
                    }
                    if ($index > 0 && str_contains($lines[$index - 1], self::IGNORE_MARKER)) {
                        continue;
                    }
                    $hits[$file . ':' . ($index + 1) . ':' . $line] = true;
                }
            }
        }
        foreach ($allowed as $path => $stillNeeded) {
            if (!$stillNeeded) {
                fwrite($this->stderr, "warning: --allow={$path} " . (is_file($root . '/' . $path) ? 'no longer contains raw form markup: remove it from the allowlist' : 'does not exist or is outside the scanned directories') . "\n");
            }
        }

        if ([] !== $hits) {
            fwrite($this->stderr, "ERROR: raw <form> / <input> / <textarea> / <select> in Twig (REQ-TWIG-005):\n" . implode("\n", array_keys($hits)) . "\n");

            return 1;
        }
        fwrite($this->stdout, 'check-no-raw-html-form: OK (' . implode(' ', $dirs) . ")\n");

        return 0;
    }

    private function isFormTheme(string $file): bool
    {
        return str_starts_with($file, 'src/Resources/views/form/')
            || str_starts_with($file, 'src/Resources/views/Form/')
            || str_contains(basename($file), 'theme');
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
