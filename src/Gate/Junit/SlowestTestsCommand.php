<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Junit;

/**
 * `bin/slowest-tests`: print the slowest test files of a JUnit log (PHPUnit / ParaTest `--log-junit`), the test case
 * times summed per file, so the long pole of a suite is visible after every run.
 *
 * Usage: slowest-tests [--limit=<n>] [--strip-prefix=<path>]... [<junit.xml>] [<limit>]
 *
 * Exit codes: 0 report printed, 2 usage error or missing / unreadable log.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class SlowestTestsCommand
{
    public const USAGE = <<<'TXT'
        Usage: slowest-tests [--limit=<n>] [--strip-prefix=<path>]... [<junit.xml>] [<limit>]

        Prints the slowest test files of a JUnit log (PHPUnit / ParaTest --log-junit), test case times summed per file
        (the class when a test case has no file attribute), with their share of the total test time.
          <junit.xml>              default: var/coverage/junit.xml
          <limit>, --limit=<n>     files printed at most (default: 20)
          --strip-prefix=<path>    prefix removed from test file paths, repeatable
                                   (default: the current directory, e.g. /app inside a container)
        Exit codes: 0 report printed, 2 usage error or missing / unreadable log (root not testsuites / testsuite).
        TXT;

    public const DEFAULT_LOG = 'var/coverage/junit.xml';

    private const DEFAULT_LIMIT = 20;

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
        $limit = null;
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
            if ('--limit' === $name) {
                $limit = $value;
            } elseif ('--strip-prefix' === $name) {
                $prefixes[] = $value;
            } else {
                return $this->usageError("Unknown option {$arg}");
            }
        }
        if (\count($positional) > 2) {
            return $this->usageError('Expected [<junit.xml>] [<limit>]');
        }
        $file = $positional[0] ?? self::DEFAULT_LOG;
        $limit ??= $positional[1] ?? (string) self::DEFAULT_LIMIT;
        if (1 !== preg_match('/^[1-9][0-9]*$/', $limit)) {
            return $this->usageError('<limit> must be a positive integer');
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
        if (false === $xml || !\in_array($xml->getName(), ['testsuites', 'testsuite'], true)) {
            fwrite($this->stderr, "ERROR: unreadable JUnit log {$file}\n");

            return 2;
        }

        /** @var array<string, float> $perFile */
        $perFile = [];
        $total = 0.0;
        foreach ($xml->xpath('//testcase') ?: [] as $case) {
            $name = (string) $case['file'];
            if ('' === $name) {
                $name = '' !== (string) $case['class'] ? (string) $case['class'] : '?';
            }
            $name = $this->stripPrefixes($name, $prefixes);
            $time = (float) $case['time'];
            $perFile[$name] = ($perFile[$name] ?? 0.0) + $time;
            $total += $time;
        }
        arsort($perFile);

        fwrite($this->stdout, \sprintf("Slowest test files (%d files, %.1fs of test time in total):\n", \count($perFile), $total));
        foreach (\array_slice($perFile, 0, (int) $limit, true) as $name => $time) {
            fwrite($this->stdout, \sprintf("  %7.1fs  %4.1f%%  %s\n", $time, $total > 0 ? 100 * $time / $total : 0, $name));
        }

        return 0;
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
