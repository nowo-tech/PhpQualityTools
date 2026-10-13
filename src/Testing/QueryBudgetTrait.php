<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing;

use function count;

use PHPUnit\Framework\Assert;

/**
 * PHPUnit helpers for SQL query budgets (anti N+1 gates).
 *
 * The using test provides the {@see QueryCounter} fed by {@see Dbal\QueryCountingMiddleware}:
 *
 * ```php
 * protected function getQueryCounter(): QueryCounter
 * {
 *     return static::getContainer()->get(QueryCounter::class);
 * }
 * ```
 *
 * The counter is read again after the operation: when the kernel was rebooted in between
 * (KernelBrowser reboots before each request), the new counter only holds the operation's queries.
 *
 * Needs `phpunit/phpunit` and `doctrine/dbal` ^4 (dev dependencies of the host).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
trait QueryBudgetTrait
{
    abstract protected function getQueryCounter(): QueryCounter;

    /**
     * Runs `$operation` and returns the SQL statements it executed.
     *
     * @return list<string>
     */
    protected function recordQueries(callable $operation): array
    {
        $counter = $this->getQueryCounter();
        $offset = $counter->count();
        $operation();
        $after = $this->getQueryCounter();

        return $after === $counter ? $after->queries($offset) : $after->queries();
    }

    /**
     * Runs `$operation` and returns how many SQL statements it executed.
     */
    protected function countQueries(callable $operation): int
    {
        return \count($this->recordQueries($operation));
    }

    /**
     * Fails when `$operation` executes more than `$max` SQL statements; returns the count.
     */
    protected function assertQueryBudget(int $max, callable $operation, string $message = ''): int
    {
        $queries = $this->recordQueries($operation);
        $count = \count($queries);

        Assert::assertLessThanOrEqual($max, $count, \sprintf(
            "%sExecuted %d SQL statement(s), budget %d. Investigate N+1 or missing joins before raising the budget.\n%s",
            '' !== $message ? $message . "\n" : '',
            $count,
            $max,
            self::describeQueries($queries),
        ));

        return $count;
    }

    /**
     * Fails when the query count of `$operation` grows with the data set size N.
     *
     * For each N of `$sizes` (strictly increasing), `$prepare(N, previousN)` brings the data set to N rows
     * (e.g. persists rows [previousN, N); previousN is 0 the first time), then `$operation` is measured.
     * With `$warmUp`, `$operation` runs once unmeasured after the first preparation (first-visit side effects).
     * The largest count may exceed the smallest by at most `$tolerance`.
     *
     * @param callable(int, int): mixed $prepare
     * @param list<int>                 $sizes
     *
     * @return array<int, int> Query count by N
     */
    protected function assertScalesWith(
        callable $prepare,
        callable $operation,
        array $sizes = [5, 50],
        int $tolerance = 0,
        bool $warmUp = true,
        string $message = '',
    ): array {
        if (\count($sizes) < 2) {
            throw new \InvalidArgumentException('assertScalesWith() needs at least two sizes.');
        }
        if ($tolerance < 0) {
            throw new \InvalidArgumentException('assertScalesWith() tolerance must be >= 0.');
        }

        $counts = [];
        $previous = 0;
        foreach ($sizes as $size) {
            if ($size < 0 || ([] !== $counts && $size <= $previous)) {
                throw new \InvalidArgumentException('assertScalesWith() sizes must be non-negative and strictly increasing.');
            }
            $prepare($size, $previous);
            if ($warmUp && [] === $counts) {
                $operation();
            }
            $counts[$size] = $this->countQueries($operation);
            $previous = $size;
        }

        $min = min($counts);
        $max = max($counts);
        $parts = [];
        foreach ($counts as $size => $count) {
            $parts[] = \sprintf('%d for N=%d', $count, $size);
        }
        $sizesList = \array_keys($counts);

        Assert::assertLessThanOrEqual($min + $tolerance, $max, \sprintf(
            '%sQuery count grows with N (N=%d..%d): %s (tolerance %d). An N+1 grows with the data set.',
            '' !== $message ? $message . "\n" : '',
            $sizesList[0],
            $sizesList[\count($sizesList) - 1],
            \implode(', ', $parts),
            $tolerance,
        ));

        return $counts;
    }

    /**
     * @param list<string> $queries
     */
    private static function describeQueries(array $queries, int $limit = 50): string
    {
        if ([] === $queries) {
            return '';
        }
        $lines = [];
        foreach (\array_slice($queries, 0, $limit) as $index => $sql) {
            $lines[] = \sprintf('  #%d %s', $index + 1, $sql);
        }
        if (\count($queries) > $limit) {
            $lines[] = \sprintf('  ... %d more', \count($queries) - $limit);
        }

        return "Statements:\n" . \implode("\n", $lines);
    }
}
