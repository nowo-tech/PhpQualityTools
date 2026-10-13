<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing\Dbal;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use NowoTech\PhpQualityTools\Testing\QueryCounter;

/**
 * @internal created by {@see QueryCountingConnection}
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class QueryCountingStatement extends AbstractStatementMiddleware
{
    public function __construct(
        Statement $statement,
        private readonly QueryCounter $counter,
        private readonly string $sql,
    ) {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        $this->counter->record($this->sql);

        return parent::execute();
    }
}
