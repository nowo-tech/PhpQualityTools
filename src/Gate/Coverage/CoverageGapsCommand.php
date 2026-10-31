<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage;

/**
 * `bin/coverage-gaps`: list the files below 100 % coverage with their uncovered lines, so a failed coverage gate says
 * where to add tests.
 *
 * Reads a Clover XML report (PHPUnit `--coverage-clover`: `<line type="stmt" count="0">`) or an LCOV report (Vitest,
 * Jest, c8: `DA:<line>,0`). The format is detected from the content (`<` first: Clover), not from the extension.
 *
 * Usage: coverage-gaps [--strip-prefix=<path>]... <report>
 *
 * Exit codes: 0 every file fully covered, 1 files with uncovered lines listed, 2 usage error or missing / unreadable
 * report.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CoverageGapsCommand
{
    public const USAGE = <<<'TXT'
        Usage: coverage-gaps [--strip-prefix=<path>]... <report>

        Lists the files below 100 % coverage of a Clover XML (PHPUnit) or LCOV (Vitest, Jest, c8) report with their
        uncovered lines ("12, 20-24"). The format is detected from the content.
          --strip-prefix=<path>    prefix removed from file paths, repeatable
                                   (default: the current directory, e.g. /app inside a container)
        Exit codes: 0 fully covered, 1 files with uncovered lines listed, 2 usage error or missing / unreadable report.
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
        $positional = [];
        $prefixes = [];
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            if (str_starts_with($arg, '--strip-prefix=') && '--strip-prefix=' !== $arg) {
                $prefixes[] = substr($arg, \strlen('--strip-prefix='));
                continue;
            }
            if (str_starts_with($arg, '--')) {
                return $this->usageError("Unknown or empty option {$arg}");
            }
            $positional[] = $arg;
        }
        if (1 !== \count($positional)) {
            return $this->usageError('Expected exactly one <report>');
        }
        $file = $positional[0];
        $path = str_starts_with($file, '/') ? $file : $this->cwd . '/' . $file;
        $content = is_file($path) ? file_get_contents($path) : false;
        if (false === $content) {
            fwrite($this->stderr, "ERROR: coverage report not found: {$file}\n");

            return 2;
        }

        $gaps = str_starts_with(ltrim($content), '<') ? $this->cloverGaps($content) : $this->lcovGaps($content);
        if (null === $gaps) {
            fwrite($this->stderr, "ERROR: unreadable Clover report {$file}\n");

            return 2;
        }
        if ([] === $gaps) {
            fwrite($this->stdout, "coverage-gaps: every file is fully covered.\n");

            return 0;
        }

        $prefixes = [] !== $prefixes ? $prefixes : [$this->cwd];
        fwrite($this->stdout, 'coverage-gaps: ' . \count($gaps) . " file(s) below 100 % (uncovered lines):\n");
        foreach ($gaps as $name => $lines) {
            fwrite($this->stdout, \sprintf("  %-80s %s\n", $this->stripPrefixes($name, $prefixes), implode(', ', self::compactRanges($lines))));
        }

        return 1;
    }

    /**
     * @param list<int> $lines
     *
     * @return list<string> "12", "20-24" …
     */
    public static function compactRanges(array $lines): array
    {
        $lines = array_values(array_unique($lines));
        sort($lines);
        $ranges = [];
        $start = $previous = null;
        foreach ($lines as $line) {
            if (null !== $previous && $line === $previous + 1) {
                $previous = $line;
                continue;
            }
            if (null !== $start) {
                $ranges[] = $start === $previous ? (string) $start : $start . '-' . $previous;
            }
            $start = $previous = $line;
        }
        if (null !== $start) {
            $ranges[] = $start === $previous ? (string) $start : $start . '-' . $previous;
        }

        return $ranges;
    }

    /**
     * @return array<string, list<int>>|null File => uncovered statement lines; null when the XML is unreadable
     */
    private function cloverGaps(string $content): ?array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (false === $xml) {
            return null;
        }

        $gaps = [];
        foreach ($xml->xpath('//file') ?: [] as $file) {
            foreach ($file->line as $line) {
                if ('stmt' === (string) $line['type'] && '0' === (string) $line['count']) {
                    $gaps[(string) $file['name']][] = (int) $line['num'];
                }
            }
        }

        return $gaps;
    }

    /**
     * @return array<string, list<int>> File => lines with zero hits
     */
    private function lcovGaps(string $content): array
    {
        $gaps = [];
        $current = '';
        foreach (preg_split('/\R/', $content) ?: [] as $row) {
            if (str_starts_with($row, 'SF:')) {
                $current = substr($row, 3);
            } elseif (str_starts_with($row, 'DA:')) {
                $parts = explode(',', substr($row, 3));
                if ('0' === trim($parts[1] ?? '0')) {
                    $gaps[$current][] = (int) $parts[0];
                }
            }
        }

        return $gaps;
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
