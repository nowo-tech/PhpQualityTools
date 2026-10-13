<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Coverage\CloverReport;
use NowoTech\PhpQualityTools\Gate\Coverage\CoverageThresholdCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CoverageThresholdCommandTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../Fixtures/clover/';

    public function testReportReadsStatementsAndDistinctLines(): void
    {
        $report = CloverReport::fromFile(self::FIXTURES . 'partial.xml');

        self::assertSame(9, $report->coveredStatements);
        self::assertSame(12, $report->totalStatements);
        self::assertSame(9, $report->coveredLines);
        self::assertSame(10, $report->totalLines);
        self::assertEqualsWithDelta(75.0, $report->statementPercent(), 0.001);
        self::assertEqualsWithDelta(90.0, $report->linePercent(), 0.001);
        self::assertTrue($report->hasLines());
    }

    public function testReportWithoutStatementsIsZeroPercent(): void
    {
        $report = new CloverReport(0, 0, 0, 0);

        self::assertSame(0.0, $report->statementPercent());
        self::assertSame(0.0, $report->linePercent());
        self::assertFalse($report->hasLines());
    }

    public function testInformationalWithoutMinimum(): void
    {
        [$code, $out, $err] = $this->execute(['full.xml']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Statement coverage: 100.00% (4/4) from ', $out);
        self::assertStringContainsString('Line coverage: 100.00% (4/4) from ', $out);
        self::assertStringContainsString('Threshold: disabled', $out);
        self::assertSame('', $err);
    }

    public function testFullCoveragePassesHundred(): void
    {
        [$code, $out] = $this->execute(['full.xml', '100']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Threshold: OK (Statement coverage 100.00% >= 100.00%)', $out);
        self::assertStringContainsString('Threshold: OK (Line coverage 100.00% >= 100.00%)', $out);
    }

    /**
     * @param list<string>          $args
     * @param array<string, string> $env
     */
    #[DataProvider('partialProvider')]
    public function testPartialCoverage(array $args, array $env, int $expected, string $expectedErr): void
    {
        [$code, , $err] = $this->execute(['partial.xml', ...$args], $env);

        self::assertSame($expected, $code);
        if ('' === $expectedErr) {
            self::assertSame('', $err);
        } else {
            self::assertStringContainsString($expectedErr, $err);
        }
    }

    /**
     * @return iterable<string, array{list<string>, array<string, string>, int, string}>
     */
    public static function partialProvider(): iterable
    {
        yield 'both under 80' => [['80'], [], 1, 'ERROR: Statement coverage 75.00% is below threshold 80.00%'];
        yield 'both at 75' => [['--min=75'], [], 0, ''];
        yield 'lines only at 80' => [['80', '--metric=lines'], [], 0, ''];
        yield 'lines only at 95' => [['--metric=lines', '--min=95'], [], 1, 'ERROR: Line coverage 90.00% is below threshold 95.00%'];
        yield 'statements only at 76' => [['--metric=statements'], ['COVERAGE_MIN' => '76'], 1, 'Statement coverage 75.00%'];
        yield 'env minimum' => [[], ['COVERAGE_MIN' => '70'], 0, ''];
        yield 'empty argument falls back to env' => [['--min='], ['COVERAGE_MIN' => '99'], 1, 'below threshold 99.00%'];
        yield 'invalid minimum' => [['abc'], [], 2, 'minimum coverage must be between 0 and 100 (got abc)'];
        yield 'minimum over 100' => [['101'], [], 2, 'between 0 and 100'];
        yield 'negative minimum' => [['--min=-1'], [], 2, 'between 0 and 100'];
        yield 'bad metric' => [['--metric=branches'], [], 2, '--metric must be one of'];
        yield 'unknown option' => [['--verbose'], [], 2, 'Unknown option --verbose'];
        yield 'too many arguments' => [['80', 'extra'], [], 2, 'Expected <clover.xml>'];
    }

    public function testMissingCloverArgumentIsUsageError(): void
    {
        [$code, , $err] = $this->execute([]);

        self::assertSame(2, $code);
        self::assertStringContainsString('Usage: check-coverage-threshold', $err);
    }

    public function testHelp(): void
    {
        [$code, $out] = $this->execute(['--help']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: check-coverage-threshold', $out);
    }

    public function testMissingReportExitsOne(): void
    {
        [$code, , $err] = $this->execute(['does-not-exist.xml', '80']);

        self::assertSame(1, $code);
        self::assertStringContainsString('ERROR: Clover report not found at ', $err);
        self::assertStringContainsString('--coverage-clover', $err);
    }

    #[DataProvider('brokenProvider')]
    public function testBrokenReportExitsTwo(string $file, string $expected): void
    {
        [$code, , $err] = $this->execute([$file]);

        self::assertSame(2, $code);
        self::assertStringContainsString($expected, $err);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function brokenProvider(): iterable
    {
        yield 'not xml' => ['invalid.xml', 'Unable to parse Clover XML'];
        yield 'no metrics' => ['no-metrics.xml', 'Missing project/metrics'];
    }

    public function testMetricsOnlyReportSkipsLines(): void
    {
        [$code, $out] = $this->execute(['metrics-only.xml', '60']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Statement coverage: 66.66% (2/3)', $out);
        self::assertStringContainsString('Line coverage: n/a', $out);

        [$code, , $err] = $this->execute(['metrics-only.xml', '60', '--metric=lines']);
        self::assertSame(2, $code);
        self::assertStringContainsString('--metric=lines needs <line> entries', $err);
    }

    /**
     * @param list<string>          $args
     * @param array<string, string> $env
     *
     * @return array{int, string, string}
     */
    private function execute(array $args, array $env = []): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $args = array_map(static fn (string $arg): string => str_ends_with($arg, '.xml') ? self::FIXTURES . $arg : $arg, $args);
        $code = (new CoverageThresholdCommand($stdout, $stderr, $env))->run($args);

        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
