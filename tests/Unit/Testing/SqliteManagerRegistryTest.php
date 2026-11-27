<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Tools\SchemaTool;
use NowoTech\PhpQualityTools\Testing\SqliteManagerRegistry;
use NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Orm\Widget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqliteManagerRegistry::class)]
final class SqliteManagerRegistryTest extends TestCase
{
    private const ENTITY_NAMESPACE = 'NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Orm';

    /**
     * @param list<object> $listeners
     */
    private function registry(array $listeners = []): SqliteManagerRegistry
    {
        $directory = __DIR__ . '/../../Fixtures/Testing/Orm';

        return new SqliteManagerRegistry(
            ['default' => [self::ENTITY_NAMESPACE => $directory], 'other' => [self::ENTITY_NAMESPACE => $directory]],
            'default',
            $listeners,
        );
    }

    private function manager(SqliteManagerRegistry $registry, string $name): EntityManagerInterface
    {
        $manager = $registry->getManager($name);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    public function testEveryManagerHasItsOwnInMemoryConnection(): void
    {
        $registry = $this->registry();

        self::assertSame('default', $registry->getDefaultManagerName());
        self::assertSame(['default' => 'em.default', 'other' => 'em.other'], $registry->getManagerNames());
        $default = $this->manager($registry, 'default');
        $other = $this->manager($registry, 'other');
        self::assertNotSame($default->getConnection(), $other->getConnection());
        self::assertSame($registry->getConnection('default'), $default->getConnection());

        (new SchemaTool($default))->createSchema([$default->getClassMetadata(Widget::class)]);
        $widget = new Widget();
        $widget->setName('bolt');
        $default->persist($widget);
        $default->flush();

        self::assertSame(1, (int) $default->getConnection()->fetchOne('SELECT COUNT(*) FROM widget'));
        $this->expectException(\Throwable::class);
        $other->getConnection()->fetchOne('SELECT COUNT(*) FROM widget');
    }

    public function testResetManagerReplacesTheManagerAndKeepsTheConnection(): void
    {
        $registry = $this->registry();
        $before = $this->manager($registry, 'default');
        $connection = $before->getConnection();
        (new SchemaTool($before))->createSchema([$before->getClassMetadata(Widget::class)]);
        $widget = new Widget();
        $widget->setName('nut');
        $before->persist($widget);
        $before->flush();

        $registry->resetManager('default');
        $after = $this->manager($registry, 'default');

        self::assertNotSame($before, $after);
        self::assertSame($connection, $after->getConnection());
        self::assertCount(1, $after->getRepository(Widget::class)->findAll());
    }

    public function testMetadataListenersSeeEveryManagerClassMetadata(): void
    {
        $listener = new class {
            /** @var list<string> */
            public array $seen = [];

            public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
            {
                $this->seen[] = $args->getClassMetadata()->getName();
            }
        };
        $registry = $this->registry([$listener]);

        $this->manager($registry, 'default')->getClassMetadata(Widget::class);

        self::assertSame([Widget::class], $listener->seen);
    }
}
