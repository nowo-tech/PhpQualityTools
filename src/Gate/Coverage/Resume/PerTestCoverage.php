<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage\Resume;

/**
 * Per-test line coverage in a form independent of the phpunit/php-code-coverage version: for each file, each
 * executable line maps the id of every test that executed it to its hit count (1 when the driver does not count
 * hits); `null` is a line the driver reported as not executable. The test results are kept as the library gave them,
 * keyed by test id.
 *
 * A test id is `<test class>::<method>[#<data set>]` (a PHPT test: its file path).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class PerTestCoverage
{
    /**
     * @param array<string, array<int, array<string, int>|null>> $lines file => line => test id => hits
     * @param array<string, mixed>                               $tests test id => result (opaque)
     */
    public function __construct(
        public array $lines,
        public array $tests,
    ) {
    }

    /**
     * The test class of a test id (a PHPT test: the id itself).
     */
    public static function testClass(string $testId): string
    {
        $position = strpos($testId, '::');

        return false === $position ? $testId : substr($testId, 0, $position);
    }

    /**
     * Without the data of these files: their line numbers belong to the old content.
     *
     * @param list<string> $files
     */
    public function withoutFiles(array $files): self
    {
        return new self(array_diff_key($this->lines, array_flip($files)), $this->tests);
    }

    /**
     * Without every contribution of the tests of these classes, on every line of every file: what those tests
     * execute now comes from the new run only, so a line they no longer reach cannot stay covered.
     *
     * @param list<string> $classes
     */
    public function withoutTestClasses(array $classes): self
    {
        $drop = array_flip($classes);
        $keep = static fn (string $id): bool => !isset($drop[self::testClass($id)]);
        $lines = [];
        foreach ($this->lines as $file => $fileLines) {
            foreach ($fileLines as $line => $hits) {
                $lines[$file][$line] = null === $hits ? null : array_filter($hits, $keep, \ARRAY_FILTER_USE_KEY);
            }
        }

        return new self($lines, array_filter($this->tests, $keep, \ARRAY_FILTER_USE_KEY));
    }

    /**
     * Union with another run: a line executable in either is executable, its tests are the union of both (a test
     * present in both keeps the higher hit count); the other run's results win.
     */
    public function merge(self $other): self
    {
        $lines = $this->lines;
        foreach ($other->lines as $file => $fileLines) {
            foreach ($fileLines as $line => $hits) {
                $mine = $lines[$file][$line] ?? null;
                if (null === $hits) {
                    $lines[$file][$line] = $mine;
                    continue;
                }
                $union = $mine ?? [];
                foreach ($hits as $id => $count) {
                    $union[$id] = max($count, $union[$id] ?? 0);
                }
                $lines[$file][$line] = $union;
            }
            ksort($lines[$file]);
        }
        ksort($lines);

        return new self($lines, array_merge($this->tests, $other->tests));
    }

    /**
     * Ids of the tests that executed at least one line of the file.
     *
     * @return list<string>
     */
    public function testsOfFile(string $file): array
    {
        $ids = [];
        foreach ($this->lines[$file] ?? [] as $hits) {
            foreach ($hits ?? [] as $id => $count) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Whether a recorded test executed a line of the file between $first and $last (inclusive). Code run only from the
     * bootstrap, an extension, a warm-up or a subprocess has executable lines no test executed.
     */
    public function hasExecutedLine(string $file, int $first, int $last): bool
    {
        foreach ($this->lines[$file] ?? [] as $line => $hits) {
            if (null !== $hits && [] !== $hits && $line >= $first && $line <= $last) {
                return true;
            }
        }

        return false;
    }

    /**
     * Executable lines and the executed ones, over every file.
     *
     * @return array{0: int, 1: int} [executed, executable]
     */
    public function lineTotals(): array
    {
        $executed = 0;
        $executable = 0;
        foreach ($this->lines as $fileLines) {
            foreach ($fileLines as $hits) {
                if (null !== $hits) {
                    ++$executable;
                    $executed += [] === $hits ? 0 : 1;
                }
            }
        }

        return [$executed, $executable];
    }
}
