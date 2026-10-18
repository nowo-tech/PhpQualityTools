<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Integration\Testing;

use NowoTech\PhpQualityTools\Testing\RouteQueryScalingTrait;
use NowoTech\PhpQualityTools\Tests\Fixtures\ProfilerKernel\FakeObjectManager;
use NowoTech\PhpQualityTools\Tests\Fixtures\ProfilerKernel\ProfilerTestKernel;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * RouteQueryScalingTrait against a real kernel, profiler and `db` data collector.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class RouteQueryScalingTraitTest extends WebTestCase
{
    use RouteQueryScalingTrait;

    protected static function getKernelClass(): string
    {
        return ProfilerTestKernel::class;
    }

    protected function setUp(): void
    {
        (new Filesystem())->remove(ProfilerTestKernel::rowsFile());
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(ProfilerTestKernel::varDir());
        parent::tearDownAfterClass();
    }

    public function testMeasuresTheDbCollectorQueryCount(): void
    {
        $client = self::createClient();

        self::assertSame(2, $this->measureRouteQueries($client, '/constant'));
        self::assertSame(1, $this->measureRouteQueries($client, '/n-plus-one'));
        self::assertSame(2, $this->assertRouteQueryBudget($client, '/constant', 2));
    }

    public function testBudgetFailsAboveTheMaximum(): void
    {
        $client = self::createClient();

        try {
            $this->assertRouteQueryBudget($client, '/constant', 1, 'Constant page');
            self::fail('Expected a budget failure.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString("Constant page\nRoute /constant ran 2 SQL queries, budget 1.", $e->getMessage());
        }
    }

    public function testBudgetFailureWithoutMessage(): void
    {
        $client = self::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Route /constant ran 2 SQL queries, budget 0.');
        $this->assertRouteQueryBudget($client, '/constant', 0);
    }

    public function testConstantRoutePassesAndSeedsThroughTheObjectManager(): void
    {
        $client = self::createClient();
        $calls = [];

        $counts = $this->assertRouteQueriesIndependentOfRows($client, '/constant', static function (?object $manager, int $from, int $to) use (&$calls): void {
            self::assertInstanceOf(FakeObjectManager::class, $manager);
            $manager->persistRows($to - $from);
            $calls[] = [$from, $to];
        }, rowMarker: 'Row 49');

        self::assertSame([2, 2], $counts);
        self::assertSame([[0, 5], [5, 50]], $calls);
    }

    public function testCustomSizesAndTolerance(): void
    {
        $client = self::createClient();

        $counts = $this->assertRouteQueriesIndependentOfRows($client, '/n-plus-one', static function (FakeObjectManager $manager, int $from, int $to): void {
            $manager->persistRows($to - $from);
        }, small: 1, large: 2, tolerance: 1);

        self::assertSame([2, 3], $counts);
    }

    public function testNPlusOneRouteFails(): void
    {
        $client = self::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Route /n-plus-one ran 6 queries with 5 rows and 51 with 50 rows (tolerance 0): an N+1 grows with the list.');
        $this->assertRouteQueriesIndependentOfRows($client, '/n-plus-one', static function (FakeObjectManager $manager, int $from, int $to): void {
            $manager->persistRows($to - $from);
        });
    }

    public function testFewerQueriesWithMoreRowsAlsoFails(): void
    {
        $client = self::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Route /fewer ran 3 queries with 5 rows and 1 with 50 rows');
        $this->assertRouteQueriesIndependentOfRows($client, '/fewer', static function (FakeObjectManager $manager, int $from, int $to): void {
            $manager->persistRows($to - $from);
        });
    }

    public function testRowMarkerMustRender(): void
    {
        $client = self::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Route /constant did not render the seeded rows.');
        $this->assertRouteQueriesIndependentOfRows($client, '/constant', static function (): void {
        }, rowMarker: 'Row 49');
    }

    public function testWarmUpMustSucceed(): void
    {
        $client = self::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Warm-up GET /missing returned HTTP 404.');
        $this->assertRouteQueriesIndependentOfRows($client, '/missing', static function (): void {
        });
    }

    public function testMeasuredRequestMustSucceed(): void
    {
        $client = self::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('GET /missing returned HTTP 404.');
        $this->measureRouteQueries($client, '/missing');
    }

    public function testInvalidSizes(): void
    {
        $client = self::createClient();

        $this->expectException(\InvalidArgumentException::class);
        $this->assertRouteQueriesIndependentOfRows($client, '/constant', static function (): void {
        }, small: 5, large: 5);
    }

    public function testNegativeSmallSize(): void
    {
        $client = self::createClient();

        $this->expectException(\InvalidArgumentException::class);
        $this->assertRouteQueriesIndependentOfRows($client, '/constant', static function (): void {
        }, small: -1);
    }

    public function testNegativeTolerance(): void
    {
        $client = self::createClient();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tolerance must be >= 0');
        $this->assertRouteQueriesIndependentOfRows($client, '/constant', static function (): void {
        }, tolerance: -1);
    }

    public function testWithoutObjectManagerTheSeedGetsNull(): void
    {
        $client = self::createClient(['environment' => 'nodb']);
        $managers = [];

        $this->seedRouteRows(static function (?object $manager) use (&$managers): void {
            $managers[] = $manager;
        }, 0, 1);

        self::assertSame([null], $managers);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The profile has no "db" collector');
        $this->measureRouteQueries($client, '/constant');
    }

    public function testCollectorWithoutQueryCount(): void
    {
        $client = self::createClient(['environment' => 'badcollector']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has no getQueryCount() method.');
        $this->measureRouteQueries($client, '/constant');
    }

    public function testProfilerMustBeEnabled(): void
    {
        $client = self::createClient(['environment' => 'noprofiler']);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('No profile for GET /constant: enable framework.profiler in the test environment.');
        $this->measureRouteQueries($client, '/constant');
    }

    public function testFlushAndClearAreCalled(): void
    {
        self::createClient();
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(FakeObjectManager::class, $manager);

        $this->seedRouteRows(static function (FakeObjectManager $m): void {
            $m->persistRows(3);
        }, 0, 3);

        self::assertSame(1, $manager->flushes);
        self::assertSame(1, $manager->clears);
    }
}
