<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage\Resume;

/**
 * Reads and writes PHPUnit `--coverage-php` files across phpunit/php-code-coverage versions.
 *
 * - Up to 11 (PHPUnit 10 / 11): the file returns a serialized `CodeCoverage` object; each executable line lists the
 *   ids of the tests that executed it. The merged file and the Clover report are written by the library itself
 *   (`Report\PHP`, `Report\Clover`) from the new run's object with the merged data.
 * - Later versions (PHPUnit 12+): the file returns an array (`codeCoverage`, `basePath`, `testResults`,
 *   `buildInformation`) whose data maps each line to test indexes and hit counts (`testIds()` resolves them). The
 *   merged file keeps the new run's header and build information; the Clover report comes from `Report\Facade`.
 *
 * Both shapes are read by duck typing, so the library is only needed when a report is written. The library classes
 * are internal to phpunit/php-code-coverage, which is why a resume refuses data produced by another library version.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class CoverageFormat
{
    private const LIBRARY = 'SebastianBergmann\\CodeCoverage\\';

    /** The project name PHPUnit gives its own Clover report. */
    private const CLOVER_NAME = 'Clover Coverage';

    public function __construct(
        private string $cloverClass = self::LIBRARY . 'Report\\Clover',
        private string $phpReportClass = self::LIBRARY . 'Report\\PHP',
        private string $facadeClass = self::LIBRARY . 'Report\\Facade',
        private string $dataClass = self::LIBRARY . 'Data\\ProcessedCodeCoverageData',
    ) {
    }

    /**
     * @throws \RuntimeException when the file is missing or is not PHPUnit coverage data
     */
    public function load(string $file): LoadedCoverage
    {
        if (!is_file($file)) {
            throw new \RuntimeException("coverage data not found: {$file}");
        }
        $payload = (static fn (string $path): mixed => include $path)($file);
        if (\is_object($payload) && method_exists($payload, 'getData') && method_exists($payload, 'getTests')) {
            $data = $this->call($payload, 'getData', true);
            /** @var array<string, mixed> $tests */
            $tests = $this->call($payload, 'getTests');

            return $this->loaded($data, '', $tests, $payload, $this->legacyDriver($payload));
        }
        if (\is_array($payload) && isset($payload['codeCoverage']) && \is_object($payload['codeCoverage'])) {
            /** @var array<string, mixed> $tests */
            $tests = \is_array($payload['testResults'] ?? null) ? $payload['testResults'] : [];
            $basePath = \is_string($payload['basePath'] ?? null) ? $payload['basePath'] : '';
            $build = \is_array($payload['buildInformation'] ?? null) ? $payload['buildInformation'] : [];
            $driver = json_encode([$build['runtime'] ?? null, $build['phpCodeCoverage']['driverInformation'] ?? null], \JSON_UNESCAPED_SLASHES);
            $payload['header'] = (string) strtok((string) file_get_contents($file), "\n");

            return $this->loaded($payload['codeCoverage'], $basePath, $tests, $payload, (string) $driver);
        }

        throw new \RuntimeException("not PHPUnit --coverage-php data (or the phpunit/php-code-coverage classes are not loaded): {$file}");
    }

    /**
     * Writes the merged data in the template's format to $coverageFile and as a Clover report to $cloverFile.
     */
    public function write(LoadedCoverage $template, PerTestCoverage $merged, string $coverageFile, string $cloverFile): void
    {
        foreach ([$coverageFile, $cloverFile] as $file) {
            if (!is_dir(\dirname($file))) {
                mkdir(\dirname($file), 0o777, true);
            }
        }
        $native = $template->native;
        if (\is_object($native)) {
            $lists = [];
            foreach ($merged->lines as $file => $lines) {
                foreach ($lines as $line => $hits) {
                    $lists[$file][$line] = null === $hits ? null : array_keys($hits);
                }
            }
            $this->call($this->call($native, 'getData', true), 'setLineCoverage', $lists);
            $this->call($native, 'setTests', $merged->tests);
            $this->call(new $this->cloverClass(), 'process', $native, $cloverFile, self::CLOVER_NAME);
            $this->call(new $this->phpReportClass(), 'process', $native, $coverageFile);

            return;
        }

        $index = [];
        $indexed = [];
        foreach ($merged->lines as $file => $lines) {
            foreach ($lines as $line => $hits) {
                if (null === $hits) {
                    $indexed[$file][$line] = null;
                    continue;
                }
                $map = [];
                foreach ($hits as $id => $count) {
                    $map[$index[$id] ??= \count($index)] = $count;
                }
                $indexed[$file][$line] = $map;
            }
        }
        $data = new $this->dataClass($template->countsHits);
        $this->call($data, 'setTestIds', array_flip($index));
        $this->call($data, 'setLineCoverage', $indexed);
        $header = $native['header'];
        unset($native['header']);
        $native['codeCoverage'] = $data;
        $native['basePath'] = '';
        $native['testResults'] = $merged->tests;
        file_put_contents(
            $coverageFile,
            $header . "\nreturn \\unserialize(<<<'END_OF_COVERAGE_SERIALIZATION'\n" . serialize($native) . "\nEND_OF_COVERAGE_SERIALIZATION\n);",
        );
        $this->call($this->call($this->facadeClass, 'fromSerializedData', $native), 'renderClover', $cloverFile, self::CLOVER_NAME);
    }

    /**
     * @param array<string, mixed>        $tests
     * @param object|array<string, mixed> $native
     */
    private function loaded(object $data, string $basePath, array $tests, object|array $native, string $driver): LoadedCoverage
    {
        $testIds = method_exists($data, 'testIds') ? $this->call($data, 'testIds') : [];
        $prefix = '' === $basePath ? '' : rtrim($basePath, '/') . '/';
        $lines = [];
        foreach ($this->call($data, 'lineCoverage') as $file => $fileLines) {
            foreach ($fileLines as $line => $value) {
                if (null === $value) {
                    $lines[$prefix . $file][$line] = null;
                    continue;
                }
                $hits = [];
                foreach ($value as $key => $entry) {
                    // A list of test ids (up to 11), or test index => hit count.
                    \is_string($entry) ? $hits[$entry] = 1 : $hits[(string) ($testIds[$key] ?? $key)] = (int) $entry;
                }
                $lines[$prefix . $file][$line] = $hits;
            }
        }

        return new LoadedCoverage(
            new PerTestCoverage($lines, $tests),
            $native,
            $driver,
            [] !== $this->call($data, 'functionCoverage'),
            method_exists($data, 'collectsHitCounts') && $this->call($data, 'collectsHitCounts'),
        );
    }

    /**
     * Calls a library method by name: the library's classes are internal and differ between versions, so they are
     * used by duck typing (a class name calls a static method).
     *
     * @param object|class-string|string $target
     */
    private function call(object|string $target, string $method, mixed ...$args): mixed
    {
        return \is_string($target) ? $target::{$method}(...$args) : $target->{$method}(...$args);
    }

    private function legacyDriver(object $coverage): string
    {
        foreach (['pcov' => 'driverIsPcov', 'xdebug' => 'driverIsXdebug'] as $name => $method) {
            if (method_exists($coverage, $method) && $this->call($coverage, $method)) {
                return $name . ' ' . (phpversion($name) ?: '?');
            }
        }

        return 'unknown';
    }
}
