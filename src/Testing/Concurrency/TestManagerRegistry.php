<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing\Concurrency;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\Proxy;

/**
 * Minimal ManagerRegistry over one connection and one entity manager; `resetManager()` builds a new manager on the
 * same connection, as DoctrineBundle does in a long-running worker, and counts the resets in {@see self::$resets}.
 *
 * ```php
 * $registry = new TestManagerRegistry($connection, static fn (Connection $c): EntityManagerInterface => new EntityManager($c, $config));
 * ```
 *
 * Needs `doctrine/orm` ^3.0 (`doctrine/persistence`, `doctrine/dbal`).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class TestManagerRegistry extends AbstractManagerRegistry
{
    public int $resets = 0;

    private EntityManagerInterface $manager;

    /**
     * @param \Closure(Connection): EntityManagerInterface $managerFactory
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly \Closure $managerFactory,
    ) {
        parent::__construct('test', ['default' => 'connection'], ['default' => 'manager'], 'default', 'default', Proxy::class);
        $this->manager = ($this->managerFactory)($this->connection);
    }

    protected function getService(string $name): object
    {
        return 'connection' === $name ? $this->connection : $this->manager;
    }

    protected function resetService(string $name): void
    {
        if ('manager' === $name) {
            ++$this->resets;
            $this->manager = ($this->managerFactory)($this->connection);
        }
    }
}
