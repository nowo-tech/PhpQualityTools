<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Integration;

use NowoTech\PhpQualityTools\Gate\Coverage\Resume\CoverageFormat;
use NowoTech\PhpQualityTools\Gate\Coverage\Resume\CoverageResumeCommand;
use NowoTech\PhpQualityTools\Tests\Unit\Gate\TempTreeTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * End to end on a small sample project with real PHPUnit runs: full run, a body-only source change, the resume, and
 * the merged report equals the report of a full run on the changed code (stale lines included).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CoverageResumeEndToEndTest extends TestCase
{
    use TempTreeTrait;

    private const CALC = <<<'PHP'
        <?php
        namespace Sample;
        class Calc
        {
            public function add(int $a, int $b): int
            {
                return $a + $b;
            }

            public function half(int $a): int
            {
                if ($a > 100) {
                    return 50;
                }

                return intdiv($a, 2);
            }

            public function triple(int $a): int
            {
                return $a * 3;
            }
        }
        PHP;

    private const OTHER = <<<'PHP'
        <?php
        namespace Sample;
        class Other extends Base
        {
            public function twice(int $a): int
            {
                return (new Calc())->add($a, $a);
            }

            public function thrice(int $a): int
            {
                return (new Calc())->triple($a);
            }
        }
        PHP;

    private const BASE = <<<'PHP'
        <?php
        namespace Sample;
        abstract class Base
        {
            public function name(): string
            {
                return static::class;
            }
        }
        PHP;

    private const CALC_TEST = <<<'PHP_WRAP'
        <?php
        namespace Sample\Tests;
        use PHPUnit\Framework\Attributes\DataProvider;
        use PHPUnit\Framework\TestCase;
        use Sample\Calc;
        final class CalcTest extends TestCase
        {
            public function testAdd(): void { self::assertSame(3, (new Calc())->add(1, 2)); }
            #[DataProvider('halves')]
            public function testHalf(int $in, int $out): void { self::assertSame($out, (new Calc())->half($in)); }
            public static function halves(): iterable { yield 'small' => [4, 2]; yield [200, 50]; }
        }
        PHP_WRAP;

    private const OTHER_TEST = <<<'PHP_WRAP'
        <?php
        namespace Sample\Tests;
        use PHPUnit\Framework\TestCase;
        use Sample\Other;
        final class OtherTest extends TestCase
        {
            public function testTwice(): void { self::assertSame(4, (new Other())->twice(2)); }
            public function testThrice(): void { self::assertSame(9, (new Other())->thrice(3)); }
            public function testName(): void { self::assertSame(Other::class, (new Other())->name()); }
        }
        PHP_WRAP;

    private string $root;

    protected function setUp(): void
    {
        if (!\extension_loaded('pcov') && (!\extension_loaded('xdebug') || !str_contains((string) \ini_get('xdebug.mode'), 'coverage'))) {
            self::markTestSkipped('needs PCOV or Xdebug in coverage mode');
        }
        $autoload = \dirname(__DIR__, 2) . '/vendor/autoload.php';
        $this->root = $this->makeTree([
            'src/Calc.php' => self::CALC,
            'src/Other.php' => self::OTHER,
            'src/Base.php' => self::BASE,
            'tests/CalcTest.php' => self::CALC_TEST,
            'tests/OtherTest.php' => self::OTHER_TEST,
            'bootstrap.php' => "<?php\nrequire " . var_export($autoload, true) . ";\nspl_autoload_register(static function (string \$class): void {\n    if (str_starts_with(\$class, 'Sample\\\\') && is_file(\$file = __DIR__ . '/src/' . substr(\$class, 7) . '.php')) {\n        require \$file;\n    }\n});\n",
            'phpunit.xml' => $this->phpunitXml('<directory>tests</directory>'),
        ]);
    }

    public function testAResumedRunMergesToTheCoverageOfAFullRun(): void
    {
        self::assertSame(3, $this->cli('plan')[0]);
        $this->phpunit('phpunit.xml', 'var/run.cov', 'var/junit.xml');
        self::assertSame(0, $this->cli('save', '--coverage=var/run.cov', '--junit=var/junit.xml', '--status=passed')[0]);

        // Other::thrice() no longer calls Calc::triple(): only a full run or a correct merge sees triple() uncovered.
        file_put_contents($this->root . '/src/Other.php', str_replace('return (new Calc())->triple($a);', 'return $a * 3;', self::OTHER));
        [$code, $out, $err] = $this->cli('plan');
        self::assertSame(0, $code, $err);
        self::assertSame("tests/OtherTest.php\n", $out);

        file_put_contents($this->root . '/phpunit-resume.xml', $this->phpunitXml('<file>tests/OtherTest.php</file>'));
        $this->phpunit('phpunit-resume.xml', 'var/part.cov', 'var/part.xml');
        [$code, , $err] = $this->cli('merge', '--coverage=var/part.cov', '--junit=var/part.xml', '--clover=var/resumed.xml');
        self::assertSame(0, $code, $err);

        $this->phpunit('phpunit.xml', 'var/full.cov', 'var/full-junit.xml', 'var/full.xml');

        self::assertSame($this->clover('var/full.xml'), $this->clover('var/resumed.xml'));
        self::assertStringContainsString('count="0"', $this->clover('var/resumed.xml'), 'triple() is uncovered after the merge');
        self::assertSame($this->perTest('var/full.cov'), $this->perTest('var/coverage/resume/merged.cov'));
    }

    private function phpunitXml(string $suite): string
    {
        return <<<XML
            <?xml version="1.0"?>
            <phpunit bootstrap="bootstrap.php" cacheDirectory="var/phpunit-cache">
              <testsuites><testsuite name="sample">{$suite}</testsuite></testsuites>
              <source><include><directory>src</directory></include></source>
            </phpunit>
            XML;
    }

    private function phpunit(string $config, string $coverage, string $junit, ?string $clover = null): void
    {
        $command = [\PHP_BINARY, \dirname(__DIR__, 2) . '/vendor/bin/phpunit', '-c', $config, '--coverage-php', $coverage, '--log-junit', $junit];
        if (null !== $clover) {
            $command[] = '--coverage-clover';
            $command[] = $clover;
        }
        $process = new Process($command, $this->root, null, null, 120.0);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
    }

    /**
     * The Clover report without its timestamps.
     */
    private function clover(string $file): string
    {
        return (string) preg_replace('/ (generated|timestamp)="\d+"/', '', (string) file_get_contents($this->root . '/' . $file));
    }

    /**
     * @return array<string, array<int, list<string>|null>>
     */
    private function perTest(string $file): array
    {
        $lines = [];
        foreach ((new CoverageFormat())->load($this->root . '/' . $file)->coverage->lines as $path => $fileLines) {
            foreach ($fileLines as $line => $hits) {
                $ids = null === $hits ? null : array_map(strval(...), array_keys($hits));
                if (null !== $ids) {
                    sort($ids);
                }
                $lines[$path][$line] = $ids;
            }
        }

        return $lines;
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    private function cli(string ...$args): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        $code = (new CoverageResumeCommand($stdout, $stderr, $this->root))->run(array_values($args));
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
