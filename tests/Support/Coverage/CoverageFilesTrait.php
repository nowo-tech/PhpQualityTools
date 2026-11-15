<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Support\Coverage;

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\PHP;

/**
 * Writes `--coverage-php` files from chosen per-test data: real php-code-coverage objects (the version this package
 * installs) and the array payload of php-code-coverage 12+ (with {@see FakeSerializedData}).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
trait CoverageFilesTrait
{
    /**
     * @param non-empty-string                             $target
     * @param array<string, array<int, list<string>|null>> $lines  file => line => test ids (the files must exist)
     * @param array<string, mixed>                         $tests  test id => result
     */
    private function writeLegacyCoverage(string $target, array $lines, array $tests = []): void
    {
        $filter = new Filter();
        foreach (array_keys($lines) as $file) {
            $filter->includeFile($file);
        }
        $coverage = new CodeCoverage(new FakeCoverageDriver(), $filter);
        $coverage->getData(true)->setLineCoverage($lines);
        $coverage->setTests($tests);
        (new PHP())->process($coverage, $target);
    }

    /**
     * @param array<string, array<int, array<int, int>|null>> $lines     file (relative to $basePath) => line => test index => hits
     * @param array<int, string>                              $testIds
     * @param array<string, mixed>                            $tests
     * @param array<string, mixed>                            $functions
     */
    private function writeSerializedCoverage(string $target, array $lines, array $testIds, array $tests = [], string $basePath = '', array $functions = [], bool $countsHits = true, string $runtime = '8.4.0'): void
    {
        $data = new FakeSerializedData($countsHits);
        $data->lines = $lines;
        $data->testIds = $testIds;
        $data->functions = $functions;
        $payload = [
            'buildInformation' => ['runtime' => ['name' => 'PHP', 'version' => $runtime], 'phpCodeCoverage' => ['version' => '14.5.1', 'driverInformation' => ['name' => 'pcov']]],
            'basePath' => $basePath,
            'codeCoverage' => $data,
            'testResults' => $tests,
        ];
        file_put_contents($target, "<?php // phpunit/php-code-coverage serialization format 4\nreturn \\unserialize(" . var_export(serialize($payload), true) . ');');
    }
}
