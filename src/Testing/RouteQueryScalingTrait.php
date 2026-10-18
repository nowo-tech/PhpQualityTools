<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing;

use Closure;
use PHPUnit\Framework\Assert;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * Route-level SQL query budgets and N+1 scaling checks through the Symfony profiler (functional tests).
 *
 * Measures the query count of the `db` data collector (DoctrineBundle's `DoctrineDataCollector`, or any
 * collector named `db` with a `getQueryCount()` method) for one request of a {@see KernelBrowser}:
 *
 * ```php
 * final class IssueListTest extends WebTestCase
 * {
 *     use RouteQueryScalingTrait;
 *
 *     public function testIssueListDoesNotScaleWithIssues(): void
 *     {
 *         $client = static::createClient();
 *         $this->assertRouteQueriesIndependentOfRows($client, '/issues', static function (EntityManagerInterface $em, int $from, int $to): void {
 *             for ($i = $from; $i < $to; ++$i) {
 *                 $em->persist(new Issue('Issue '.$i));
 *             }
 *         }, rowMarker: 'Issue 49');
 *     }
 * }
 * ```
 *
 * Needs `phpunit/phpunit`, `symfony/framework-bundle` + `symfony/browser-kit`, `framework.profiler` enabled in the test
 * environment (`collect: false` is enough: {@see KernelBrowser::enableProfiler()} collects the next request) and a `db`
 * collector (DoctrineBundle with `doctrine.dbal.profiling_collect_backtrace` or the default profiling). Independent of
 * {@see QueryBudgetTrait} (DBAL middleware counter): both can be used in the same test class.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
trait RouteQueryScalingTrait
{
    /**
     * The test container (provided by Symfony's KernelTestCase / WebTestCase).
     */
    abstract protected static function getContainer(): ContainerInterface;

    /**
     * Requests `$path` with the profiler enabled, asserts a 2xx response and returns the `db` collector query count.
     */
    protected function measureRouteQueries(KernelBrowser $client, string $path, string $method = 'GET'): int
    {
        $client->enableProfiler();
        $client->request($method, $path);
        $status = $client->getResponse()->getStatusCode();
        Assert::assertTrue($status >= 200 && $status < 300, \sprintf('%s %s returned HTTP %d.', $method, $path, $status));

        $profile = $client->getProfile();
        Assert::assertInstanceOf(Profile::class, $profile, \sprintf('No profile for %s %s: enable framework.profiler in the test environment.', $method, $path));
        Assert::assertTrue($profile->hasCollector('db'), 'The profile has no "db" collector: install DoctrineBundle (or register a "db" data collector).');
        $collector = $profile->getCollector('db');
        if (!method_exists($collector, 'getQueryCount')) {
            throw new \LogicException(\sprintf('The "db" data collector (%s) has no getQueryCount() method.', $collector::class));
        }

        $count = $collector->getQueryCount();
        Assert::assertIsInt($count);

        return $count;
    }

    /**
     * Fails when one request to `$path` runs more than `$max` SQL queries; returns the count.
     */
    protected function assertRouteQueryBudget(KernelBrowser $client, string $path, int $max, string $message = ''): int
    {
        $count = $this->measureRouteQueries($client, $path);

        Assert::assertLessThanOrEqual($max, $count, \sprintf(
            '%sRoute %s ran %d SQL queries, budget %d. Investigate N+1 or missing joins before raising the budget.',
            '' !== $message ? $message . "\n" : '',
            $path,
            $count,
            $max,
        ));

        return $count;
    }

    /**
     * Fails when the query count of `GET $path` grows with the number of rows it lists.
     *
     * Seeds rows [0, $small), warms the route up once unmeasured (first-visit side effects such as
     * "recently opened" rows), measures; seeds rows [$small, $large) and measures again. The two counts
     * may differ by at most `$tolerance`. `$rowMarker` (text of a seeded row) must appear in the second
     * response, so an empty list cannot pass by accident.
     *
     * `$seed` is called as `$seed($objectManager, $from, $to)` and persists rows [$from, $to); the object manager
     * ({@see routeScalingObjectManager()}, the ORM entity manager by default, or null without Doctrine ORM) is
     * flushed and cleared afterwards.
     *
     * @param \Closure $seed Closure(object|null $objectManager, int $from, int $to): void
     *
     * @return array{0: int, 1: int} Query counts with `$small` and with `$large` rows
     */
    protected function assertRouteQueriesIndependentOfRows(
        KernelBrowser $client,
        string $path,
        \Closure $seed,
        int $small = 5,
        int $large = 50,
        ?string $rowMarker = null,
        int $tolerance = 0,
    ): array {
        if ($small < 0 || $large <= $small) {
            throw new \InvalidArgumentException('assertRouteQueriesIndependentOfRows() needs 0 <= $small < $large.');
        }
        if ($tolerance < 0) {
            throw new \InvalidArgumentException('assertRouteQueriesIndependentOfRows() tolerance must be >= 0.');
        }

        $this->seedRouteRows($seed, 0, $small);
        $client->request('GET', $path);
        $status = $client->getResponse()->getStatusCode();
        Assert::assertTrue($status >= 200 && $status < 300, \sprintf('Warm-up GET %s returned HTTP %d.', $path, $status));

        $smallCount = $this->measureRouteQueries($client, $path);
        $this->seedRouteRows($seed, $small, $large);
        $largeCount = $this->measureRouteQueries($client, $path);
        if (null !== $rowMarker) {
            Assert::assertStringContainsString($rowMarker, (string) $client->getResponse()->getContent(), \sprintf('Route %s did not render the seeded rows.', $path));
        }

        Assert::assertLessThanOrEqual($tolerance, abs($largeCount - $smallCount), \sprintf(
            'Route %s ran %d queries with %d rows and %d with %d rows (tolerance %d): an N+1 grows with the list.',
            $path,
            $smallCount,
            $small,
            $largeCount,
            $large,
            $tolerance,
        ));

        return [$smallCount, $largeCount];
    }

    /**
     * Runs `$seed($objectManager, $from, $to)`, then flushes and clears the object manager (when there is one).
     */
    protected function seedRouteRows(\Closure $seed, int $from, int $to): void
    {
        $manager = $this->routeScalingObjectManager();
        $seed($manager, $from, $to);
        if (null === $manager) {
            return;
        }
        if (method_exists($manager, 'flush')) {
            $manager->flush();
        }
        if (method_exists($manager, 'clear')) {
            $manager->clear();
        }
    }

    /**
     * Object manager handed to the seed closures: `doctrine.orm.entity_manager` when the container has it.
     * Override to seed through another manager (e.g. MongoDB ODM) or a custom service.
     */
    protected function routeScalingObjectManager(): ?object
    {
        $container = static::getContainer();
        if (!$container->has('doctrine.orm.entity_manager')) {
            return null;
        }

        return $container->get('doctrine.orm.entity_manager');
    }
}
