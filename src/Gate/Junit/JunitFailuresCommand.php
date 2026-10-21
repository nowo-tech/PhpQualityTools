<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Junit;

/**
 * `bin/junit-failures`: print only the failed / errored tests of a JUnit log (PHPUnit / ParaTest `--log-junit`).
 *
 * Usage: junit-failures [--max=<n>] [--lines=<n>] [--strip-prefix=<path>]... <junit.xml> [<max>] [<lines>]
 *
 * One block per failed test: `class::method (file:line)` and the first lines of the failure / error message.
 * Prints nothing when every test passed, so it can follow any PHPUnit run in a Makefile or CI step.
 *
 * Exit codes: 0 no failed test, 1 failed tests listed, 2 usage error or missing / unreadable log.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class JunitFailuresCommand
{
    public const USAGE = <<<'TXT'
        Usage: junit-failures [--max=<n>] [--lines=<n>] [--strip-prefix=<path>]... <junit.xml> [<max>] [<lines>]

        Prints only the failed / errored tests of a JUnit log (PHPUnit / ParaTest --log-junit):
        class::method (file:line) and the first lines of the message. Prints nothing when every test passed.
          <max>, --max=<n>         failed tests printed at most (default: 15; the rest are counted)
          <lines>, --lines=<n>     message lines printed per test (default: 6)
          --strip-prefix=<path>    prefix removed from test file paths, repeatable
                                   (default: the current directory, e.g. /app inside a container)
        Exit codes: 0 no failed test, 1 failed tests listed, 2 usage error or missing / unreadable log.
        TXT;

    private const DEFAULT_MAX = 15;

    private const DEFAULT_LINES = 6;

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
        $positional = [];
        $max = null;
        $lines = null;
        $prefixes = [];
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            if (!str_starts_with($arg, '--')) {
                $positional[] = $arg;
                continue;
            }
            [$name, $value] = str_contains($arg, '=') ? explode('=', $arg, 2) : [$arg, ''];
            if ('' === $value) {
                return $this->usageError("Unknown or empty option {$arg}");
            }
            if ('--max' === $name) {
                $max = $value;
            } elseif ('--lines' === $name) {
                $lines = $value;
            } elseif ('--strip-prefix' === $name) {
                $prefixes[] = $value;
            } else {
                return $this->usageError("Unknown option {$arg}");
            }
        }
        if ([] === $positional || \count($positional) > 3) {
            return $this->usageError('Expected <junit.xml> [<max>] [<lines>]');
        }
        $file = $positional[0];
        $max = $this->positiveInt($max ?? $positional[1] ?? (string) self::DEFAULT_MAX);
        $lines = $this->positiveInt($lines ?? $positional[2] ?? (string) self::DEFAULT_LINES);
        if (null === $max || null === $lines) {
            return $this->usageError('<max> and <lines> must be positive integers');
        }
        $prefixes = [] !== $prefixes ? $prefixes : [$this->cwd];

        $path = str_starts_with($file, '/') ? $file : $this->cwd . '/' . $file;
        if (!is_file($path)) {
            fwrite($this->stderr, "ERROR: no JUnit log at {$file}\n");

            return 2;
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (false === $xml) {
            fwrite($this->stderr, "ERROR: unreadable JUnit log {$file}\n");

            return 2;
        }

        $failed = [];
        foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $case) {
            $problem = $case->failure[0] ?? $case->error[0];
            $message = array_values(array_filter(
                array_map(rtrim(...), explode("\n", trim((string) $problem))),
                static fn (string $line): bool => '' !== $line,
            ));
            $where = $this->stripPrefixes((string) $case['file'], $prefixes);
            if ('' !== (string) $case['line']) {
                $where .= ':' . $case['line'];
            }
            $name = ('' !== (string) $case['class'] ? $case['class'] . '::' : '') . $case['name'];
            $failed[] = '  ' . $name . ('' !== $where ? " ({$where})" : '')
                . implode('', array_map(static fn (string $line): string => "\n      " . $line, \array_slice($message, 0, $lines)));
        }
        if ([] === $failed) {
            return 0;
        }

        fwrite($this->stdout, \sprintf("Failed tests in %s (%d):\n", $file, \count($failed)));
        fwrite($this->stdout, implode("\n", \array_slice($failed, 0, $max)) . "\n");
        if (\count($failed) > $max) {
            fwrite($this->stdout, \sprintf("  … and %d more (see the JUnit log).\n", \count($failed) - $max));
        }

        return 1;
    }

    private function positiveInt(string $value): ?int
    {
        return 1 === preg_match('/^[1-9][0-9]*$/', $value) ? (int) $value : null;
    }

    /**
     * @param list<string> $prefixes
     */
    private function stripPrefixes(string $path, array $prefixes): string
    {
        foreach ($prefixes as $prefix) {
            $prefix = rtrim($prefix, '/') . '/';
            if (str_starts_with($path, $prefix)) {
                return substr($path, \strlen($prefix));
            }
        }

        return $path;
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "ERROR: {$message}\n" . self::USAGE . "\n");

        return 2;
    }
}
