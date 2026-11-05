<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\WallClock;

/**
 * `bin/check-wallclock-fixtures`: fail on tests and fixtures that read the real clock without freezing it.
 *
 * Usage: check-wallclock-fixtures [--root=<dir>] [--paths=<dir>]... [--exclude=<pattern>]...
 *        [--baseline=<file>] [--write-baseline] [--frozen-marker=<regex>]... [--warn-only]
 *
 * Every PHP file under the paths, and the JS / TS test files (`*.test.*`, `*.spec.*`, or under a `tests`, `test`,
 * `__tests__`, `e2e`, `fixtures` or `__fixtures__` directory), are scanned by {@see WallClockScanner}.
 * The baseline (ratchet) file lists files that may keep their findings (reported as warnings); a listed file
 * without findings any more is an error, so the list only shrinks. `--write-baseline` (re)writes it.
 *
 * Exit codes: 0 OK (or only baselined findings / --warn-only), 1 findings or stale baseline entries, 2 usage error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class WallClockFixturesCommand
{
    public const USAGE = <<<'TXT'
        Usage: check-wallclock-fixtures [--root=<dir>] [--paths=<dir>]... [--exclude=<pattern>]...
                                        [--baseline=<file>] [--write-baseline] [--frozen-marker=<regex>]...
                                        [--warn-only]

        Fails on tests / fixtures that read the real clock without freezing it: PHP new DateTime[Immutable]() or
        a relative date ('today', 'tomorrow', '+1 day', 'next monday' ...), strtotime('<relative>'), time(),
        date() without a timestamp, Carbon::now(); JS / TS new Date() in test files.
        Files that freeze the clock (MockClock, ClockSensitiveTrait / mockTime(), ClockMock / time-sensitive,
        setTestNow(); useFakeTimers(), setSystemTime(), mockdate, page.clock.install()) are skipped.
        "@wallclock-ignore" in a comment on the same line or the line above skips one finding.
          --root=<dir>            project root; every other path is relative to it (default: current directory)
          --paths=<dir>           directory or file to scan, repeatable (default: tests)
          --exclude=<pattern>     path, directory prefix or fnmatch glob to skip, repeatable
          --baseline=<file>       ratchet file: one path per line (# comments); listed files only warn,
                                  a listed file without findings fails (remove it from the list)
          --write-baseline        write every file with findings to --baseline and exit 0
          --frozen-marker=<regex> extra PCRE (with delimiters) that marks a file as using a frozen clock, repeatable
          --warn-only             report findings as warnings and exit 0 (adoption mode)
        Skips vendor/, node_modules/ and var/. A missing default directory is skipped.
        Exit codes: 0 OK, 1 findings or stale baseline entries, 2 usage error.
        TXT;

    private const JS_EXTENSIONS = ['js', 'mjs', 'cjs', 'jsx', 'ts', 'mts', 'cts', 'tsx'];

    private const JS_TEST_DIRS = '#(^|/)(tests?|__tests__|e2e|fixtures|__fixtures__)/#';

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
        $paths = [];
        $exclude = [];
        $baseline = null;
        $writeBaseline = false;
        $markers = [];
        $warnOnly = false;
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            [$name, $value] = str_contains($arg, '=') ? explode('=', $arg, 2) : [$arg, null];
            if ('--write-baseline' === $arg) {
                $writeBaseline = true;
            } elseif ('--warn-only' === $arg) {
                $warnOnly = true;
            } elseif (null === $value || '' === $value) {
                return $this->usageError("Unknown or empty option {$arg}");
            } elseif ('--root' === $name) {
                $root = str_starts_with($value, '/') ? $value : $this->cwd . '/' . $value;
            } elseif ('--paths' === $name) {
                $paths[] = $this->normalize($value);
            } elseif ('--exclude' === $name) {
                $exclude[] = $this->normalize($value);
            } elseif ('--baseline' === $name) {
                $baseline = $this->normalize($value);
            } elseif ('--frozen-marker' === $name) {
                if (false === @preg_match($value, '')) {
                    return $this->usageError("--frozen-marker is not a valid PCRE pattern (with delimiters): {$value}");
                }
                $markers[] = $value;
            } else {
                return $this->usageError("Unknown option {$arg}");
            }
        }
        if ($writeBaseline && null === $baseline) {
            return $this->usageError('--write-baseline needs --baseline=<file>');
        }

        $root = rtrim($root, '/');
        if (!is_dir($root)) {
            fwrite($this->stderr, "ERROR: root directory not found: {$root}\n");

            return 2;
        }
        foreach ($paths as $path) {
            if (!file_exists($root . '/' . $path)) {
                fwrite($this->stderr, "ERROR: path not found: {$path} (under {$root})\n");

                return 2;
            }
        }
        if ([] === $paths) {
            $paths = is_dir($root . '/tests') ? ['tests'] : [];
        }

        $scanner = new WallClockScanner($markers);
        $files = $this->files($root, $paths, $exclude);
        $findings = [];
        foreach ($files as $relative => $kind) {
            $source = (string) file_get_contents($root . '/' . $relative);
            $found = 'php' === $kind ? $scanner->scanPhp($source) : $scanner->scanJs($source);
            if ([] !== $found) {
                $findings[$relative] = $found;
            }
        }

        if ($writeBaseline) {
            return $this->writeBaseline($root . '/' . $baseline, array_keys($findings));
        }

        $listed = [];
        if (null !== $baseline) {
            $listed = $this->readBaseline($root . '/' . $baseline);
            if (null === $listed) {
                fwrite($this->stderr, "ERROR: baseline file not found: {$baseline} (create it with --write-baseline)\n");

                return 2;
            }
        }

        $errors = [];
        $warnings = [];
        foreach ($findings as $relative => $found) {
            $baselined = isset($listed[$relative]);
            foreach ($found as [$line, $message]) {
                $entry = "{$relative}:{$line} {$message}";
                if ($baselined || $warnOnly) {
                    $warnings[] = 'warning: ' . $entry . ($baselined ? ' [baseline]' : '');
                } else {
                    $errors[] = 'error: ' . $entry;
                }
            }
        }
        foreach (array_keys($listed) as $path) {
            if (!isset($findings[$path])) {
                $errors[] = "error: baseline entry {$path} " . (isset($files[$path]) ? 'has no wall-clock finding any more: remove it from the baseline' : 'does not exist or is outside the scanned paths');
            }
        }

        if ([] !== $warnings) {
            fwrite($this->stderr, implode("\n", $warnings) . "\n");
        }
        if ([] !== $errors) {
            fwrite($this->stderr, implode("\n", $errors) . "\n");
            fwrite($this->stderr, \sprintf("Wall-clock fixtures: %d error(s), %d warning(s).\n", \count($errors), \count($warnings)));

            return 1;
        }
        fwrite($this->stdout, \sprintf(
            "OK: wall-clock fixtures (%d file(s) scanned, %d warning(s), %d baselined file(s)).\n",
            \count($files),
            \count($warnings),
            \count($listed),
        ));

        return 0;
    }

    /**
     * @param list<string> $files
     */
    private function writeBaseline(string $file, array $files): int
    {
        if (!is_dir(\dirname($file))) {
            fwrite($this->stderr, 'ERROR: baseline directory not found: ' . \dirname($file) . "\n");

            return 2;
        }
        $content = "# check-wallclock-fixtures baseline: files that still read the real clock. Only remove lines.\n"
            . implode('', array_map(static fn (string $path): string => $path . "\n", $files));
        file_put_contents($file, $content);
        fwrite($this->stdout, \sprintf("OK: wrote %d file(s) to the wall-clock baseline %s.\n", \count($files), $file));

        return 0;
    }

    /**
     * @return array<string, true>|null
     */
    private function readBaseline(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $listed = [];
        foreach (explode("\n", (string) file_get_contents($file)) as $line) {
            $line = trim($line);
            if ('' !== $line && !str_starts_with($line, '#')) {
                $listed[$this->normalize($line)] = true;
            }
        }

        return $listed;
    }

    /**
     * @param list<string> $paths
     * @param list<string> $exclude
     *
     * @return array<string, 'php'|'js'> relative path => kind, sorted by path
     */
    private function files(string $root, array $paths, array $exclude): array
    {
        $files = [];
        foreach ($paths as $path) {
            $absolute = $root . '/' . $path;
            $candidates = is_file($absolute)
                ? [$path]
                : array_map(
                    static fn (\SplFileInfo $file): string => substr($file->getPathname(), \strlen($root) + 1),
                    array_filter(
                        iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS)), false),
                        static fn (\SplFileInfo $file): bool => $file->isFile(),
                    ),
                );
            foreach ($candidates as $relative) {
                $kind = $this->kind($relative, is_file($absolute));
                if (null !== $kind && !preg_match('#(^|/)(vendor|node_modules|var)/#', $relative) && !$this->excluded($relative, $exclude)) {
                    $files[$relative] = $kind;
                }
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * @return 'php'|'js'|null
     */
    private function kind(string $relative, bool $explicit): ?string
    {
        $extension = strtolower(pathinfo($relative, \PATHINFO_EXTENSION));
        if ('php' === $extension) {
            return 'php';
        }
        if (!\in_array($extension, self::JS_EXTENSIONS, true) || str_ends_with($relative, '.d.ts')) {
            return null;
        }

        return $explicit || 1 === preg_match('/\.(test|spec)\.[^.\/]+$/', $relative) || 1 === preg_match(self::JS_TEST_DIRS, $relative) ? 'js' : null;
    }

    /**
     * @param list<string> $patterns
     */
    private function excluded(string $file, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (false !== strpbrk($pattern, '*?[') ? fnmatch($pattern, $file) : ($file === $pattern || str_starts_with($file, $pattern . '/'))) {
                return true;
            }
        }

        return false;
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
