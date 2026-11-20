<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing\Concurrency;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use NowoTech\PhpQualityTools\Testing\Concurrency\TestManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TestManagerRegistry::class)]
final class TestManagerRegistryTest extends TestCase
{
    public function testResetManagerBuildsANewManagerOnTheSameConnection(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $factoryCalls = 0;
        $registry = new TestManagerRegistry($connection, static function (Connection $connection) use (&$factoryCalls): EntityManagerInterface {
            ++$factoryCalls;
            $config = ORMSetup::createAttributeMetadataConfig([], true);
            $config->setProxyDir(sys_get_temp_dir() . '/pqt-test-proxies');
            $config->setProxyNamespace('PqtTestProxies');
            $config->setMetadataDriverImpl(new AttributeDriver([]));

            return new EntityManager($connection, $config);
        });

        $first = $registry->getManager();
        self::assertSame(1, $factoryCalls);
        self::assertSame($first, $registry->getManager());
        self::assertSame($connection, $registry->getConnection());
        self::assertSame(0, $registry->resets);

        $registry->resetManager();
        $second = $registry->getManager();

        self::assertNotSame($first, $second);
        self::assertSame(2, $factoryCalls);
        self::assertSame(1, $registry->resets);
        self::assertSame($connection, $registry->getConnection());
    }

    public function testOnlyTheManagerServiceCountsAsAReset(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $registry = new TestManagerRegistry($connection, static function (Connection $connection): EntityManagerInterface {
            $config = ORMSetup::createAttributeMetadataConfig([], true);
            $config->setProxyDir(sys_get_temp_dir() . '/pqt-test-proxies');
            $config->setProxyNamespace('PqtTestProxies');
            $config->setMetadataDriverImpl(new AttributeDriver([]));

            return new EntityManager($connection, $config);
        });

        $method = new \ReflectionMethod($registry, 'resetService');
        $method->invoke($registry, 'connection');

        self::assertSame(0, $registry->resets);
    }
}
