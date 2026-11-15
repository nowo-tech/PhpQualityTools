<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Support\Coverage;

/**
 * Stands for the `ProcessedCodeCoverageData` of php-code-coverage 12+ (test index => hit count per line, `testIds()`),
 * which this package's own PHPUnit does not install.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class FakeSerializedData
{
    /** @var array<string, array<int, array<int, int>|null>> */
    public array $lines = [];

    /** @var array<int, string> */
    public array $testIds = [];

    /** @var array<string, mixed> */
    public array $functions = [];

    public function __construct(public readonly bool $countsHits = false)
    {
    }

    /**
     * @return array<string, array<int, array<int, int>|null>>
     */
    public function lineCoverage(): array
    {
        return $this->lines;
    }

    /**
     * @param array<string, array<int, array<int, int>|null>> $lines
     */
    public function setLineCoverage(array $lines): void
    {
        $this->lines = $lines;
    }

    /**
     * @return array<string, mixed>
     */
    public function functionCoverage(): array
    {
        return $this->functions;
    }

    /**
     * @return array<int, string>
     */
    public function testIds(): array
    {
        return $this->testIds;
    }

    /**
     * @param array<int, string> $testIds
     */
    public function setTestIds(array $testIds): void
    {
        $this->testIds = $testIds;
    }

    public function collectsHitCounts(): bool
    {
        return $this->countsHits;
    }
}
