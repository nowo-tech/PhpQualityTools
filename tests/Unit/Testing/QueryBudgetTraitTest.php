<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use NowoTech\PhpQualityTools\Testing\Dbal\QueryCountingMiddleware;
use NowoTech\PhpQualityTools\Testing\QueryBudgetTrait;
use NowoTech\PhpQualityTools\Testing\QueryCounter;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the trait and the DBAL 4 middleware against an in-memory SQLite database.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class QueryBudgetTraitTest extends TestCase
{
    use QueryBudgetTrait;

    private QueryCounter $counter;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->counter = new QueryCounter();
        $this->connection = $this->connect($this->counter);
        $this->connection->executeStatement('CREATE TABLE author (id INTEGER PRIMARY KEY, name TEXT)');
        $this->connection->executeStatement('CREATE TABLE book (id INTEGER PRIMARY KEY, author_id INTEGER, title TEXT)');
    }

    protected function getQueryCounter(): QueryCounter
    {
        return $this->counter;
    }

    public function testMiddlewareCountsQueryExecAndPreparedStatements(): void
    {
        $queries = $this->recordQueries(function (): void {
            $this->connection->executeQuery('SELECT 1');
            $this->connection->executeStatement('DELETE FROM book');
            $this->connection->executeQuery('SELECT * FROM author WHERE id = ?', [1]);
            $this->connection->insert('author', ['name' => 'Ann']);
            $this->connection->transactional(static fn (Connection $c): int => (int) $c->executeStatement('DELETE FROM author'));
        });

        self::assertSame([
            'SELECT 1',
            'DELETE FROM book',
            'SELECT * FROM author WHERE id = ?',
            'INSERT INTO author (name) VALUES (?)',
            'DELETE FROM author',
        ], $queries);
    }

    public function testTransactionStatementsAreCountedOnRequest(): void
    {
        $counter = new QueryCounter();
        $connection = $this->connect($counter, true);

        $connection->beginTransaction();
        $connection->executeQuery('SELECT 1');
        $connection->commit();
        $connection->beginTransaction();
        $connection->rollBack();

        self::assertSame(['"START TRANSACTION"', 'SELECT 1', '"COMMIT"', '"START TRANSACTION"', '"ROLLBACK"'], $counter->queries());
        self::assertSame(['"ROLLBACK"'], $counter->queries(4));
        $counter->reset();
        self::assertSame(0, $counter->count());
    }

    public function testCountQueriesAndBudget(): void
    {
        $this->seedAuthors(3, 0);

        self::assertSame(1, $this->countQueries(fn (): array => $this->listWithJoin()));
        self::assertSame(4, $this->assertQueryBudget(4, fn (): array => $this->listWithNPlusOne()));
    }

    public function testBudgetFailureListsStatements(): void
    {
        $this->seedAuthors(2, 0);

        try {
            $this->assertQueryBudget(1, fn (): array => $this->listWithNPlusOne(), 'Author list');
            self::fail('Budget should have failed.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString("Author list\nExecuted 3 SQL statement(s), budget 1.", $e->getMessage());
            self::assertStringContainsString('#1 SELECT id, name FROM author', $e->getMessage());
            self::assertStringContainsString('#3 SELECT title FROM book WHERE author_id = ?', $e->getMessage());
        }
    }

    public function testBudgetFailureTruncatesLongStatementLists(): void
    {
        try {
            $this->assertQueryBudget(0, function (): void {
                for ($i = 0; $i < 52; ++$i) {
                    $this->connection->executeQuery('SELECT ' . $i);
                }
            });
            self::fail('Budget should have failed.');
        } catch (AssertionFailedError $e) {
            self::assertStringStartsWith('Executed 52 SQL statement(s), budget 0.', $e->getMessage());
            self::assertStringContainsString('#50 SELECT 49', $e->getMessage());
            self::assertStringContainsString('... 2 more', $e->getMessage());
        }
    }

    public function testEmptyOperationHasNoStatementList(): void
    {
        self::assertSame(0, $this->assertQueryBudget(0, static function (): void {
        }));
    }

    public function testCounterReplacedDuringOperationIsReadFromScratch(): void
    {
        $this->connection->executeQuery('SELECT 1');

        $queries = $this->recordQueries(function (): void {
            // Simulates a kernel reboot: a new container brings a new counter + connection.
            $this->counter = new QueryCounter();
            $this->connect($this->counter)->executeQuery('SELECT 2');
        });

        self::assertSame(['SELECT 2'], $queries);
    }

    public function testScalesWithPassesForJoinedList(): void
    {
        $prepared = [];
        $counts = $this->assertScalesWith(
            function (int $n, int $previous) use (&$prepared): void {
                $prepared[] = [$n, $previous];
                $this->seedAuthors($n, $previous);
            },
            fn (): array => $this->listWithJoin(),
            [2, 10, 30],
        );

        self::assertSame([[2, 0], [10, 2], [30, 10]], $prepared);
        self::assertSame([2 => 1, 10 => 1, 30 => 1], $counts);
    }

    public function testScalesWithFailsOnNPlusOne(): void
    {
        try {
            $this->assertScalesWith(
                fn (int $n, int $previous) => $this->seedAuthors($n, $previous),
                fn (): array => $this->listWithNPlusOne(),
                message: 'Authors page',
            );
            self::fail('N+1 should have been detected.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString("Authors page\nQuery count grows with N (N=5..50): 6 for N=5, 51 for N=50 (tolerance 0).", $e->getMessage());
        }
    }

    public function testScalesWithToleranceAndWarmUp(): void
    {
        $runs = 0;
        $counts = $this->assertScalesWith(
            static function (): void {
            },
            function () use (&$runs): void {
                ++$runs;
                // First run is the warm-up; the measured runs differ by one query.
                for ($i = 0; $i < min($runs, 3); ++$i) {
                    $this->connection->executeQuery('SELECT 1');
                }
            },
            [1, 2],
            tolerance: 1,
        );

        self::assertSame(3, $runs);
        self::assertSame([1 => 2, 2 => 3], $counts);

        $runs = 0;
        $this->assertScalesWith(static function (): void {
        }, static function () use (&$runs): void {
            ++$runs;
        }, [0, 1], warmUp: false);
        self::assertSame(2, $runs);
    }

    /**
     * @param list<int> $sizes
     */
    #[DataProvider('invalidScalingProvider')]
    public function testScalesWithRejectsInvalidArguments(array $sizes, int $tolerance, string $expected): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expected);

        $this->assertScalesWith(static function (): void {
        }, static function (): void {
        }, $sizes, $tolerance);
    }

    /**
     * @return iterable<string, array{list<int>, int, string}>
     */
    public static function invalidScalingProvider(): iterable
    {
        yield 'one size' => [[5], 0, 'at least two sizes'];
        yield 'negative tolerance' => [[5, 50], -1, 'tolerance must be >= 0'];
        yield 'not increasing' => [[5, 5], 0, 'strictly increasing'];
        yield 'negative size' => [[-1, 5], 0, 'non-negative'];
    }

    private function connect(QueryCounter $counter, bool $countTransactions = false): Connection
    {
        $configuration = new Configuration();
        $configuration->setMiddlewares([new QueryCountingMiddleware($counter, $countTransactions)]);

        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
    }

    private function seedAuthors(int $to, int $from): void
    {
        for ($i = $from; $i < $to; ++$i) {
            $this->connection->insert('author', ['id' => $i + 1, 'name' => 'Author ' . $i]);
            $this->connection->insert('book', ['author_id' => $i + 1, 'title' => 'Book ' . $i]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listWithJoin(): array
    {
        return $this->connection->fetchAllAssociative('SELECT a.id, a.name, b.title FROM author a LEFT JOIN book b ON b.author_id = a.id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listWithNPlusOne(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, name FROM author');
        foreach ($rows as $index => $row) {
            $rows[$index]['title'] = $this->connection->fetchOne('SELECT title FROM book WHERE author_id = ?', [$row['id']]);
        }

        return $rows;
    }
}
