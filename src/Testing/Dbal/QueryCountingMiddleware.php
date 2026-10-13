<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing\Dbal;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use NowoTech\PhpQualityTools\Testing\QueryCounter;

/**
 * Doctrine DBAL 4 middleware that records every executed SQL statement in a {@see QueryCounter}.
 *
 * Counted: `Connection::query()`, `Connection::exec()` and each `Statement::execute()` of a prepared
 * statement. Transaction boundaries (`START TRANSACTION` / `COMMIT` / `ROLLBACK`) are counted only
 * with `$countTransactions` (the Symfony profiler counts them too).
 *
 * Symfony (test env): register it with the `doctrine.middleware` tag — see docs/USAGE.md.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class QueryCountingMiddleware implements Middleware
{
    public function __construct(
        private readonly QueryCounter $counter,
        private readonly bool $countTransactions = false,
    ) {
    }

    public function wrap(Driver $driver): Driver
    {
        return new QueryCountingDriver($driver, $this->counter, $this->countTransactions);
    }
}
