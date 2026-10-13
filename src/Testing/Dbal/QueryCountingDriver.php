<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing\Dbal;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use NowoTech\PhpQualityTools\Testing\QueryCounter;

/**
 * @internal created by {@see QueryCountingMiddleware}
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class QueryCountingDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly QueryCounter $counter,
        private readonly bool $countTransactions,
    ) {
        parent::__construct($driver);
    }

    public function connect(#[\SensitiveParameter] array $params): Connection
    {
        return new QueryCountingConnection(parent::connect($params), $this->counter, $this->countTransactions);
    }
}
