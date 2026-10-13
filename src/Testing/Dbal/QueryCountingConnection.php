<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing\Dbal;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use NowoTech\PhpQualityTools\Testing\QueryCounter;

/**
 * @internal created by {@see QueryCountingDriver}
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class QueryCountingConnection extends AbstractConnectionMiddleware
{
    public function __construct(
        Connection $connection,
        private readonly QueryCounter $counter,
        private readonly bool $countTransactions,
    ) {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new QueryCountingStatement(parent::prepare($sql), $this->counter, $sql);
    }

    public function query(string $sql): Result
    {
        $this->counter->record($sql);

        return parent::query($sql);
    }

    public function exec(string $sql): int|string
    {
        $this->counter->record($sql);

        return parent::exec($sql);
    }

    public function beginTransaction(): void
    {
        $this->recordTransaction('"START TRANSACTION"');
        parent::beginTransaction();
    }

    public function commit(): void
    {
        $this->recordTransaction('"COMMIT"');
        parent::commit();
    }

    public function rollBack(): void
    {
        $this->recordTransaction('"ROLLBACK"');
        parent::rollBack();
    }

    private function recordTransaction(string $statement): void
    {
        if ($this->countTransactions) {
            $this->counter->record($statement);
        }
    }
}
