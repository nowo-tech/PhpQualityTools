<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing\Concurrency;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Repository\RepositoryFactory;

/**
 * Stands for DoctrineBundle's ContainerRepositoryFactory: keeps one repository per manager instance for the life of
 * the process, so a test can prove that a repository obtained before a manager reset is not used after it.
 *
 * ```php
 * $config->setRepositoryFactory($factory = new RetainingRepositoryFactory());
 * // … reset the manager …
 * self::assertSame(2, $factory->managers());
 * ```
 *
 * Needs `doctrine/orm` ^3.0.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class RetainingRepositoryFactory implements RepositoryFactory
{
    /** @var array<int, array<string, EntityRepository<object>>> */
    private array $repositories = [];

    /**
     * @template T of object
     *
     * @param class-string<T> $entityName
     *
     * @return EntityRepository<T>
     */
    public function getRepository(EntityManagerInterface $entityManager, string $entityName): EntityRepository
    {
        /** @var EntityRepository<T> $repository */
        $repository = $this->repositories[spl_object_id($entityManager)][$entityName] ??= new EntityRepository($entityManager, $entityManager->getClassMetadata($entityName)); // @igor-ignore - PHPUnit test-support code for the consumers' test suites, never a service of a worker application.

        return $repository;
    }

    /**
     * How many manager instances this factory holds repositories for.
     */
    public function managers(): int
    {
        return \count($this->repositories);
    }
}
