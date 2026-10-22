<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing;

/**
 * Records the SQL statements sent through {@see Dbal\QueryCountingMiddleware} (test environments only).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class QueryCounter
{
    /** @var list<string> SQL statements, in execution order */
    private array $queries = [];

    public function record(string $sql): void
    {
        // @igor-ignore - PHPUnit helper, one instance per test; never a worker service
        $this->queries[] = $sql;
    }

    public function count(): int
    {
        return \count($this->queries);
    }

    /**
     * @return list<string> Statements recorded since `$offset` (default: all)
     */
    public function queries(int $offset = 0): array
    {
        return \array_slice($this->queries, $offset);
    }

    public function reset(): void
    {
        $this->queries = [];
    }
}
