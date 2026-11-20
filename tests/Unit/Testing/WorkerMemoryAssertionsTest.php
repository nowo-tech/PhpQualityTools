<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing;

use NowoTech\PhpQualityTools\Testing\WorkerMemoryAssertions;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkerMemoryAssertions::class)]
final class WorkerMemoryAssertionsTest extends TestCase
{
    public function testSamplesOnlyEveryFiftyRequestsAfterTheWarmUp(): void
    {
        $samples = [];
        for ($request = 0; $request < 200; ++$request) {
            WorkerMemoryAssertions::sample($request, $samples);
        }

        self::assertCount(3, $samples); // requests 50, 100 and 150
        self::assertContainsOnly('int', $samples);
    }

    public function testTheWarmUpAndTheDistanceAreConfigurable(): void
    {
        $samples = [];
        for ($request = 0; $request < 10; ++$request) {
            WorkerMemoryAssertions::sample($request, $samples, 4, 3);
        }

        self::assertCount(2, $samples); // requests 6 and 9: 6 % 3 === 0 after the warm-up of 4
    }

    public function testMedianIsTheUpperMedianOfTheSortedValues(): void
    {
        self::assertSame(3, WorkerMemoryAssertions::median([5, 1, 3]));
        self::assertSame(3, WorkerMemoryAssertions::median([4, 1, 2, 3]));
        self::assertSame(7, WorkerMemoryAssertions::median([7]));
    }

    public function testGrowthIsLastMinusFirstByDefault(): void
    {
        self::assertSame(0, WorkerMemoryAssertions::growth([10, 10, 10, 10]));
        self::assertSame(90, WorkerMemoryAssertions::growth([10, 10, 100, 100]));
        // One late step counts in full.
        self::assertSame(4_999_990, WorkerMemoryAssertions::growth([10, 10, 10, 10, 10, 5_000_000]));
        self::assertSame(-5, WorkerMemoryAssertions::growth([20, 30, 15]));
    }

    public function testGrowthWithMediansIsTheDifferenceOfTheMediansOfTheTwoHalves(): void
    {
        self::assertSame(90, WorkerMemoryAssertions::growth([10, 10, 100, 100], true));
        // One late step does not move the median of the second half.
        self::assertSame(0, WorkerMemoryAssertions::growth([10, 10, 10, 10, 10, 5_000_000], true));
        // A leak raises every later sample.
        self::assertSame(2000, WorkerMemoryAssertions::growth([1000, 1100, 1200, 3000, 3100, 3200], true));
    }

    public function testCollectedMemoryIsThePositiveHeapSize(): void
    {
        self::assertGreaterThan(0, WorkerMemoryAssertions::collectedMemory());
    }

    public function testAFlatSeriesPasses(): void
    {
        WorkerMemoryAssertions::assertFlat([1_000_000, 1_000_100, 1_000_200, 1_000_100]);
        WorkerMemoryAssertions::assertFlat([100, 5_000, 100, 5_000], 1_000_000, 'custom message');
        // A late reallocation step passes in the median mode only.
        WorkerMemoryAssertions::assertFlat([1_000_000, 1_000_000, 1_000_000, 1_000_000, 1_000_000, 9_000_000], medians: true);

        $this->addToAssertionCount(1);
    }

    public function testALeakFailsAndListsTheSamples(): void
    {
        try {
            WorkerMemoryAssertions::assertFlat([1_000_000, 1_100_000, 9_000_000, 9_100_000]);
            self::fail('The leak was accepted.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('memory stays flat over many requests', $e->getMessage());
            self::assertStringContainsString('1000000, 1100000, 9000000, 9100000', $e->getMessage());
        }
    }

    public function testALateStepFailsFirstToLast(): void
    {
        $this->expectException(AssertionFailedError::class);

        WorkerMemoryAssertions::assertFlat([1_000_000, 1_000_000, 1_000_000, 1_000_000, 1_000_000, 9_000_000]);
    }

    public function testTooFewSamplesFail(): void
    {
        try {
            WorkerMemoryAssertions::assertFlat([1_000_000]);
            self::fail('A single sample was accepted.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('Too few memory samples', $e->getMessage());
        }
    }
}
