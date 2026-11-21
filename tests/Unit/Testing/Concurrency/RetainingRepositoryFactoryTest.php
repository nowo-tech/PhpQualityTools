<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing\Concurrency;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use NowoTech\PhpQualityTools\Testing\Concurrency\RetainingRepositoryFactory;
use NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Orm\Widget;
use NowoTech\PhpQualityTools\Tests\Support\Testing\OrmConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetainingRepositoryFactory::class)]
final class RetainingRepositoryFactoryTest extends TestCase
{
    private function manager(RetainingRepositoryFactory $factory): EntityManager
    {
        $config = OrmConfiguration::create([__DIR__ . '/../../../Fixtures/Testing/Orm']);
        $config->setRepositoryFactory($factory);

        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    }

    public function testKeepsOneRepositoryPerManagerInstance(): void
    {
        $factory = new RetainingRepositoryFactory();
        self::assertSame(0, $factory->managers());

        $first = $this->manager($factory);
        $repository = $first->getRepository(Widget::class);
        self::assertSame($repository, $factory->getRepository($first, Widget::class));
        self::assertSame(1, $factory->managers());

        $second = $this->manager($factory);
        self::assertNotSame($repository, $second->getRepository(Widget::class));
        self::assertSame(2, $factory->managers());
    }
}
