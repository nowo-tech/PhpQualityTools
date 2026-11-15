<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate\Coverage\Resume;

use NowoTech\PhpQualityTools\Gate\Coverage\Resume\PerTestCoverage;
use PHPUnit\Framework\TestCase;

/**
 * The merge proves stale lines are dropped: a line only a rerun test reached before no longer counts as covered.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class PerTestCoverageTest extends TestCase
{
    public function testTestClassOfAnId(): void
    {
        self::assertSame('App\Tests\FooTest', PerTestCoverage::testClass('App\Tests\FooTest::testBar#data set'));
        self::assertSame('/app/tests/x.phpt', PerTestCoverage::testClass('/app/tests/x.phpt'));
    }

    public function testDroppingAChangedFileRemovesAllItsOldLines(): void
    {
        $kept = new PerTestCoverage(['/a.php' => [3 => ['T::a' => 1]], '/b.php' => [4 => []]], ['T::a' => 'ok']);

        $without = $kept->withoutFiles(['/a.php']);

        self::assertSame(['/b.php' => [4 => []]], $without->lines);
        self::assertSame(['T::a' => 'ok'], $without->tests);
    }

    public function testDroppingTestClassesRemovesTheirContributionsEverywhere(): void
    {
        $kept = new PerTestCoverage([
            '/unchanged.php' => [10 => ['OtherTest::a' => 1], 11 => ['OtherTest::a' => 1, 'CalcTest::b' => 2], 12 => null],
        ], ['OtherTest::a' => 'ok', 'CalcTest::b' => 'ok']);

        $without = $kept->withoutTestClasses(['OtherTest']);

        self::assertSame([10 => [], 11 => ['CalcTest::b' => 2], 12 => null], $without->lines['/unchanged.php']);
        self::assertSame(['CalcTest::b' => 'ok'], $without->tests);
    }

    public function testMergeAfterDroppingLeavesAStaleLineUncovered(): void
    {
        // The kept run: OtherTest reached Calc::triple (line 20) through Other.php, which then changed.
        $kept = new PerTestCoverage([
            '/src/Calc.php' => [7 => ['CalcTest::add' => 1, 'OtherTest::twice' => 1], 20 => ['OtherTest::thrice' => 1]],
            '/src/Other.php' => [7 => ['OtherTest::twice' => 1], 11 => ['OtherTest::thrice' => 1]],
        ], ['CalcTest::add' => 'ok', 'OtherTest::twice' => 'ok', 'OtherTest::thrice' => 'ok']);
        // The rerun of OtherTest: Other.php no longer calls triple().
        $rerun = new PerTestCoverage([
            '/src/Calc.php' => [7 => ['OtherTest::twice' => 1], 20 => []],
            '/src/Other.php' => [7 => ['OtherTest::twice' => 1], 12 => ['OtherTest::thrice' => 1]],
        ], ['OtherTest::twice' => 'ok', 'OtherTest::thrice' => 'ok']);

        $merged = $kept->withoutFiles(['/src/Other.php'])->withoutTestClasses(['OtherTest'])->merge($rerun);

        self::assertSame([7 => ['CalcTest::add' => 1, 'OtherTest::twice' => 1], 20 => []], $merged->lines['/src/Calc.php'], 'line 20 is stale: no longer covered');
        self::assertSame([7 => ['OtherTest::twice' => 1], 12 => ['OtherTest::thrice' => 1]], $merged->lines['/src/Other.php'], 'old line 11 is gone');
        self::assertSame([3, 4], $merged->lineTotals());
    }

    public function testNaiveMergeWithoutDroppingWouldKeepTheStaleLine(): void
    {
        $kept = new PerTestCoverage(['/src/Calc.php' => [20 => ['OtherTest::thrice' => 1]]], []);
        $rerun = new PerTestCoverage(['/src/Calc.php' => [20 => []]], []);

        self::assertSame(['OtherTest::thrice' => 1], $kept->merge($rerun)->lines['/src/Calc.php'][20]);
        self::assertSame([], $kept->withoutTestClasses(['OtherTest'])->merge($rerun)->lines['/src/Calc.php'][20]);
    }

    public function testMergeUnionsLinesKeepsTheHigherHitCountAndTheNewResults(): void
    {
        $left = new PerTestCoverage(['/a.php' => [1 => ['A::x' => 3], 2 => null, 5 => ['A::x' => 1]], '/only-left.php' => [1 => []]], ['A::x' => 'old']);
        $right = new PerTestCoverage(['/a.php' => [1 => ['A::x' => 1, 'B::y' => 2], 2 => ['B::y' => 1], 3 => null, 5 => null], '/new.php' => [9 => []]], ['A::x' => 'new']);

        $merged = $left->merge($right);

        self::assertSame([1 => ['A::x' => 3, 'B::y' => 2], 2 => ['B::y' => 1], 3 => null, 5 => ['A::x' => 1]], $merged->lines['/a.php']);
        self::assertSame(['/a.php', '/new.php', '/only-left.php'], array_keys($merged->lines));
        self::assertSame(['A::x' => 'new'], $merged->tests);
    }

    public function testTestsOfAFileAndExecutableLines(): void
    {
        $coverage = new PerTestCoverage(['/a.php' => [1 => ['A::x' => 1], 2 => ['A::x' => 1, 'B::y' => 1], 3 => null, 4 => []]], []);

        self::assertSame(['A::x', 'B::y'], $coverage->testsOfFile('/a.php'));
        self::assertSame([], $coverage->testsOfFile('/missing.php'));
        self::assertTrue($coverage->hasExecutedLine('/a.php', 1, 1));
        self::assertTrue($coverage->hasExecutedLine('/a.php', 2, 9));
        self::assertFalse($coverage->hasExecutedLine('/a.php', 4, 9), 'QF-M1: an executable line no test executed does not count');
        self::assertFalse($coverage->hasExecutedLine('/a.php', 3, 3), 'a non-executable line');
        self::assertFalse($coverage->hasExecutedLine('/a.php', 5, 9));
        self::assertFalse($coverage->hasExecutedLine('/missing.php', 1, 9));
    }
}
