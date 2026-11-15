<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate\Coverage\Resume;

use NowoTech\PhpQualityTools\Gate\Coverage\Resume\CoverageFormat;
use NowoTech\PhpQualityTools\Gate\Coverage\Resume\PerTestCoverage;
use NowoTech\PhpQualityTools\Tests\Support\Coverage\CoverageFilesTrait;
use NowoTech\PhpQualityTools\Tests\Support\Coverage\FakeFacade;
use NowoTech\PhpQualityTools\Tests\Support\Coverage\FakeLegacyCoverage;
use NowoTech\PhpQualityTools\Tests\Support\Coverage\FakeSerializedData;
use NowoTech\PhpQualityTools\Tests\Unit\Gate\TempTreeTrait;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CoverageFormatTest extends TestCase
{
    use CoverageFilesTrait;
    use TempTreeTrait;

    private const CALC = "<?php\nnamespace App;\nclass Calc\n{\n    public function add(int \$a, int \$b): int\n    {\n        return \$a + \$b;\n    }\n\n    public function sub(int \$a, int \$b): int\n    {\n        return \$a - \$b;\n    }\n}\n";

    public function testReadsAndWritesTheInstalledLibraryFormat(): void
    {
        $root = $this->makeTree(['src/Calc.php' => self::CALC]);
        $file = $root . '/src/Calc.php';
        $this->writeLegacyCoverage($root . '/run.cov', [$file => [7 => ['A\CalcTest::testAdd', 'A\OtherTest::testX'], 12 => [], 13 => null]], ['A\CalcTest::testAdd' => ['size' => 'small', 'status' => 'success']]);

        $loaded = (new CoverageFormat())->load($root . '/run.cov');

        self::assertSame([$file => [7 => ['A\CalcTest::testAdd' => 1, 'A\OtherTest::testX' => 1], 12 => [], 13 => null]], $loaded->coverage->lines);
        self::assertSame(['A\CalcTest::testAdd' => ['size' => 'small', 'status' => 'success']], $loaded->coverage->tests);
        self::assertSame('unknown', $loaded->driver, 'the fake driver is neither PCOV nor Xdebug');
        self::assertFalse($loaded->hasBranchData);
        self::assertFalse($loaded->countsHits);

        $merged = new PerTestCoverage([$file => [7 => ['A\CalcTest::testAdd' => 1], 12 => ['A\CalcTest::testSub' => 1], 13 => null]], ['A\CalcTest::testSub' => ['size' => 'small', 'status' => 'success']]);
        (new CoverageFormat())->write($loaded, $merged, $root . '/out/merged.cov', $root . '/out/clover.xml');

        $again = (new CoverageFormat())->load($root . '/out/merged.cov');
        self::assertSame($merged->lines, $again->coverage->lines);
        self::assertSame($merged->tests, $again->coverage->tests);
        $clover = (string) file_get_contents($root . '/out/clover.xml');
        self::assertStringContainsString('<project timestamp=', $clover);
        self::assertStringContainsString('name="Clover Coverage"', $clover);
        self::assertStringContainsString('<line num="7" type="stmt" count="1"/>', $clover);
        self::assertStringContainsString('<line num="12" type="stmt" count="1"/>', $clover);
        self::assertStringContainsString('statements="2" coveredstatements="2"', $clover);
    }

    public function testReadsAndWritesTheSerializedPayloadOfLaterVersions(): void
    {
        $root = $this->makeTree([]);
        $this->writeSerializedCoverage($root . '/run.cov', [
            'Calc.php' => [7 => [0 => 3, 1 => 1], 12 => [], 13 => null],
            'sub/Other.php' => [5 => [1 => 2]],
        ], [0 => 'A\CalcTest::testAdd', 1 => 'A\OtherTest::testX'], ['A\CalcTest::testAdd' => 'ok'], '/app/src');

        $format = new CoverageFormat(facadeClass: FakeFacade::class, dataClass: FakeSerializedData::class);
        $loaded = $format->load($root . '/run.cov');

        self::assertSame([
            '/app/src/Calc.php' => [7 => ['A\CalcTest::testAdd' => 3, 'A\OtherTest::testX' => 1], 12 => [], 13 => null],
            '/app/src/sub/Other.php' => [5 => ['A\OtherTest::testX' => 2]],
        ], $loaded->coverage->lines);
        self::assertSame('[{"name":"PHP","version":"8.4.0"},{"name":"pcov"}]', $loaded->driver);
        self::assertTrue($loaded->countsHits);
        self::assertFalse($loaded->hasBranchData);

        $merged = new PerTestCoverage(['/app/src/Calc.php' => [7 => ['B\NewTest::a' => 2, 'A\CalcTest::testAdd' => 1], 12 => [], 13 => null]], ['B\NewTest::a' => 'ok']);
        $format->write($loaded, $merged, $root . '/out/merged.cov', $root . '/out/clover.json');

        $report = json_decode((string) file_get_contents($root . '/out/clover.json'), true);
        self::assertSame([
            'name' => 'Clover Coverage',
            'basePath' => '',
            'lines' => ['/app/src/Calc.php' => [7 => [0 => 2, 1 => 1], 12 => [], 13 => null]],
            'testIds' => [0 => 'B\NewTest::a', 1 => 'A\CalcTest::testAdd'],
            'countsHits' => true,
            'tests' => ['B\NewTest::a' => 'ok'],
        ], $report);
        self::assertStringStartsWith("<?php // phpunit/php-code-coverage serialization format 4\nreturn", (string) file_get_contents($root . '/out/merged.cov'));
        self::assertSame($merged->lines, $format->load($root . '/out/merged.cov')->coverage->lines, 'the merged file reads back');
    }

    public function testSerializedPayloadWithBranchDataAndMissingParts(): void
    {
        $root = $this->makeTree([]);
        $this->writeSerializedCoverage($root . '/run.cov', ['/abs/A.php' => [1 => [7 => 1]]], [], [], '', ['/abs/A.php' => ['f' => []]], false);
        file_put_contents($root . '/bare.cov', "<?php\nreturn ['codeCoverage' => new " . FakeSerializedData::class . '()];');

        $format = new CoverageFormat(dataClass: FakeSerializedData::class);
        $loaded = $format->load($root . '/run.cov');
        self::assertTrue($loaded->hasBranchData);
        self::assertSame(['/abs/A.php' => [1 => ['7' => 1]]], $loaded->coverage->lines, 'an unknown test index stays as it is');

        $bare = $format->load($root . '/bare.cov');
        self::assertSame([], $bare->coverage->tests);
        self::assertSame('[null,null]', $bare->driver);
    }

    public function testLegacyDrivers(): void
    {
        $root = $this->makeTree([]);
        foreach (['pcov', 'xdebug'] as $driver) {
            file_put_contents($root . "/{$driver}.cov", '<?php return \unserialize(' . var_export(serialize(new FakeLegacyCoverage(new FakeSerializedData(), $driver)), true) . ');');

            self::assertStringStartsWith($driver . ' ', (new CoverageFormat())->load($root . "/{$driver}.cov")->driver);
        }
    }

    public function testRefusesWhatIsNotCoverageData(): void
    {
        $root = $this->makeTree(['other.php' => '<?php return [1];']);

        foreach (['/missing.cov' => 'coverage data not found', '/other.php' => 'not PHPUnit --coverage-php data'] as $file => $message) {
            try {
                (new CoverageFormat())->load($root . $file);
                self::fail('expected an exception');
            } catch (\RuntimeException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }
}
