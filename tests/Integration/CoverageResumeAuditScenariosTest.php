<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Integration;

use NowoTech\PhpQualityTools\Gate\Coverage\Resume\CoverageResumeCommand;
use NowoTech\PhpQualityTools\Tests\Unit\Gate\TempTreeTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The false greens of audit QF (PR #74), end to end with real PHPUnit runs: a full run is kept, a function body
 * changes, and the resume must not come out green when a full run is red.
 *
 * - s9 / s9b (QF-H2): `#[CoversClass]` hides callers from the per-test data (test result, and stale merged coverage);
 * - s11b / s13a / s13b (QF-H1): a kept run that failed outside a JUnit test case (failOnDeprecation,
 *   setUpBeforeClass, data provider error);
 * - s12 (QF-M1): a changed body only the bootstrap executes.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CoverageResumeAuditScenariosTest extends TestCase
{
    use TempTreeTrait;

    private const CALC_DOUBLE = "<?php\nnamespace App;\nfinal class Calc\n{\n    public static function double(int \$x): int\n    {\n        return 2 * \$x;\n    }\n}\n";
    private const OTHER_ID = "<?php\nnamespace App;\nfinal class Other\n{\n    public static function id(int \$x): int\n    {\n        return \$x;\n    }\n}\n";
    private const CALC_TEST = "<?php\nnamespace Tests;\nuse App\\Calc;\nuse PHPUnit\\Framework\\TestCase;\nfinal class CalcTest extends TestCase\n{\n    public function testDouble(): void { self::assertSame(4, Calc::double(2)); }\n}\n";
    private const OTHER_TEST = "<?php\nnamespace Tests;\nuse App\\Other;\nuse PHPUnit\\Framework\\TestCase;\nfinal class OtherTest extends TestCase\n{\n    public function testId(): void { self::assertSame(3, Other::id(3)); }\n}\n";

    private string $root = '';

    protected function setUp(): void
    {
        $pcov = \extension_loaded('pcov') && (bool) \ini_get('pcov.enabled');
        $xdebug = \function_exists('xdebug_info') && \in_array('coverage', (array) xdebug_info('mode'), true);
        if (!$pcov && !$xdebug) {
            self::markTestSkipped('needs PCOV or Xdebug in coverage mode');
        }
    }

    public function testS9ACoversClassTestThatRunsTheChangedCodeIsRerun(): void
    {
        $this->project([
            'src/Calc.php' => self::CALC_DOUBLE,
            'src/Other.php' => self::OTHER_ID,
            'src/Wrapper.php' => "<?php\nnamespace App;\nfinal class Wrapper\n{\n    public static function double(int \$x): int\n    {\n        return Calc::double(\$x);\n    }\n}\n",
            'tests/CalcTest.php' => "<?php\nnamespace Tests;\nuse PHPUnit\\Framework\\TestCase;\nuse PHPUnit\\Framework\\Attributes\\CoversClass;\n#[CoversClass(\\App\\Calc::class)]\nfinal class CalcTest extends TestCase\n{\n    public function testDouble(): void { self::assertIsInt(\\App\\Calc::double(2)); }\n}\n",
            'tests/OtherTest.php' => self::OTHER_TEST,
            'tests/WrapperTest.php' => "<?php\nnamespace Tests;\nuse PHPUnit\\Framework\\TestCase;\nuse PHPUnit\\Framework\\Attributes\\CoversClass;\n#[CoversClass(\\App\\Wrapper::class)]\nfinal class WrapperTest extends TestCase\n{\n    public function testDouble(): void { self::assertSame(4, \\App\\Wrapper::double(2)); }\n}\n",
        ]);
        self::assertSame(0, $this->keepFullRun());
        $this->replace('src/Calc.php', 'return 2 * $x;', 'return 2 * $x + 1;');

        [$plan, $files, $tests] = $this->resume();

        self::assertSame(0, $plan);
        self::assertContains('tests/WrapperTest.php', $files, 'covers metadata: rerun on every source resume');
        self::assertNotSame(0, $tests, 'the resume is red, like a full run');
        self::assertNotSame(0, $this->truth());
    }

    public function testS9bTheMergedCoverageOfACoversClassTestIsNotStale(): void
    {
        $covers = static fn (string $class, string $body): string => "<?php\nnamespace Tests;\nuse PHPUnit\\Framework\\TestCase;\nuse PHPUnit\\Framework\\Attributes\\CoversClass;\n#[CoversClass(\\App\\{$class}::class)]\nfinal class {$class}Test extends TestCase\n{\n    {$body}\n}\n";
        $this->project([
            'src/Calc.php' => "<?php\nnamespace App;\nfinal class Calc\n{\n    public static function pick(int \$x): bool\n    {\n        return \$x > 0;\n    }\n}\n",
            'src/Other.php' => self::OTHER_ID,
            'src/Wrapper.php' => "<?php\nnamespace App;\nfinal class Wrapper\n{\n    public static function run(int \$x): string\n    {\n        if (Calc::pick(\$x)) {\n            return 'pos';\n        }\n\n        return 'neg';\n    }\n}\n",
            'tests/CalcTest.php' => $covers('Calc', 'public function testPick(): void { self::assertIsBool(\App\Calc::pick(5)); }'),
            'tests/OtherTest.php' => $covers('Other', 'public function testId(): void { self::assertSame(3, \App\Other::id(3)); }'),
            'tests/WrapperTest.php' => $covers('Wrapper', "public function testRun(): void { self::assertNotSame('', \\App\\Wrapper::run(-5)); self::assertNotSame('', \\App\\Wrapper::run(50)); }"),
        ]);
        self::assertSame(0, $this->keepFullRun());
        // Wrapper::run() no longer reaches `return 'pos'` (Wrapper.php unchanged).
        $this->replace('src/Calc.php', 'return $x > 0;', 'return $x > 100;');

        [$plan, $files, $tests] = $this->resume();

        self::assertSame(0, $plan);
        self::assertContains('tests/WrapperTest.php', $files);
        self::assertSame(0, $tests);
        $this->phpunit('phpunit.xml', 'var/truth.cov', 'var/truth.xml', 'var/truth-clover.xml');
        self::assertSame($this->clover('var/truth-clover.xml'), $this->clover('var/resumed.xml'), 'the merged report equals a full run');
        self::assertStringContainsString('<line num="8" type="stmt" count="0"/>', $this->clover('var/resumed.xml'), "return 'pos' is uncovered");
    }

    /**
     * @return iterable<string, array{array<string, string>, string, int}>
     */
    public static function failuresOutsideTestCases(): iterable
    {
        yield 's11b failOnDeprecation' => [[
            'src/Other.php' => "<?php\nnamespace App;\nfinal class Other\n{\n    public static function id(int \$x): int\n    {\n        trigger_error('old api', \\E_USER_DEPRECATED);\n\n        return \$x;\n    }\n}\n",
        ], 'failOnDeprecation="true"', 3];
        yield 's13a setUpBeforeClass error' => [[
            'tests/BrokenTest.php' => "<?php\nnamespace Tests;\nuse PHPUnit\\Framework\\TestCase;\nfinal class BrokenTest extends TestCase\n{\n    public static function setUpBeforeClass(): void { throw new \\RuntimeException('boom'); }\n    public function testX(): void { self::assertSame(1, \\App\\Other::id(1)); }\n}\n",
        ], '', 0];
        yield 's13b data provider error' => [[
            'tests/BrokenTest.php' => "<?php\nnamespace Tests;\nuse PHPUnit\\Framework\\TestCase;\nuse PHPUnit\\Framework\\Attributes\\DataProvider;\nfinal class BrokenTest extends TestCase\n{\n    public static function cases(): array { throw new \\RuntimeException('boom'); }\n    #[DataProvider('cases')]\n    public function testX(int \$v): void { self::assertSame(1, \\App\\Other::id(\$v)); }\n}\n",
        ], '', 3];
    }

    /**
     * QF-H1: the failure is not forgotten — the plan asks for the full suite (3), or the failing file is rerun and
     * stays red (0: resumed, red).
     *
     * @param array<string, string> $files
     */
    #[DataProvider('failuresOutsideTestCases')]
    public function testAKeptRunThatFailedOutsideATestCaseStaysRed(array $files, string $attributes, int $expectedPlan): void
    {
        $this->project($files + [
            'src/Calc.php' => self::CALC_DOUBLE,
            'src/Other.php' => self::OTHER_ID,
            'tests/CalcTest.php' => self::CALC_TEST,
            'tests/OtherTest.php' => self::OTHER_TEST,
        ], $attributes);
        self::assertNotSame(0, $this->keepFullRun(), 'the kept run is red');
        $this->replace('src/Calc.php', 'return 2 * $x;', 'return $x + $x;');

        [$plan, , $tests] = $this->resume();

        self::assertSame($expectedPlan, $plan);
        if (0 === $plan) {
            self::assertNotSame(0, $tests, 'the failing file is rerun and stays red');
        }
        self::assertNotSame(0, $this->truth());
    }

    public function testS12ABodyOnlyTheBootstrapRunsIsNotResumed(): void
    {
        $this->project([
            'src/Boot.php' => "<?php\nnamespace App;\nfinal class Boot\n{\n    public static function init(): void\n    {\n        Registry::\$rate = 2;\n    }\n\n    public static function version(): string\n    {\n        return '1';\n    }\n}\n",
            'src/Registry.php' => "<?php\nnamespace App;\nfinal class Registry\n{\n    public static int \$rate = 0;\n\n    public static function apply(int \$x): int\n    {\n        return \$x * self::\$rate;\n    }\n}\n",
            'tests/BRegistryTest.php' => "<?php\nnamespace Tests;\nuse PHPUnit\\Framework\\TestCase;\nfinal class BRegistryTest extends TestCase\n{\n    public function testApply(): void { self::assertSame(6, \\App\\Registry::apply(3)); }\n}\n",
            'tests/BootTest.php' => "<?php\nnamespace Tests;\nuse PHPUnit\\Framework\\TestCase;\nfinal class BootTest extends TestCase\n{\n    public function testVersion(): void { self::assertSame('1', \\App\\Boot::version()); }\n}\n",
        ], '', "\\App\\Boot::init();\n");
        self::assertSame(0, $this->keepFullRun());
        $this->replace('src/Boot.php', 'Registry::$rate = 2;', 'Registry::$rate = 3;');

        [$plan, , , $err] = $this->resume();

        self::assertSame(3, $plan);
        self::assertStringContainsString('src/Boot.php: the function at lines 6-8 changed and no test of the kept run executed it', $err);
        self::assertNotSame(0, $this->truth());
    }

    /**
     * @param array<string, string> $files
     */
    private function project(array $files, string $attributes = '', string $bootstrap = ''): void
    {
        $autoload = var_export(\dirname(__DIR__, 2) . '/vendor/autoload.php', true);
        $this->root = $this->makeTree($files + [
            'bootstrap.php' => "<?php\nrequire {$autoload};\nspl_autoload_register(static function (string \$c): void {\n    foreach (['App\\\\' => __DIR__ . '/src/', 'Tests\\\\' => __DIR__ . '/tests/'] as \$p => \$d) {\n        if (str_starts_with(\$c, \$p) && is_file(\$f = \$d . str_replace('\\\\', '/', substr(\$c, \\strlen(\$p))) . '.php')) {\n            require \$f;\n        }\n    }\n});\n{$bootstrap}",
            'phpunit.xml' => $this->phpunitXml('<directory>tests</directory>', $attributes),
        ]);
    }

    private function phpunitXml(string $suite, string $attributes = ''): string
    {
        return "<?xml version=\"1.0\"?>\n<phpunit bootstrap=\"bootstrap.php\" cacheDirectory=\"var/phpunit-cache\" {$attributes}>\n"
            . "  <testsuites><testsuite name=\"sample\">{$suite}</testsuite></testsuites>\n"
            . "  <source><include><directory>src</directory></include></source>\n</phpunit>\n";
    }

    /**
     * The full run, saved as the make recipe does: the tests' exit status apart from the overall status.
     */
    private function keepFullRun(): int
    {
        self::assertSame(3, $this->cli('plan')[0]);
        $rc = $this->phpunit('phpunit.xml', 'var/run.cov', 'var/junit.xml');
        $status = 0 === $rc ? 'passed' : 'failed';
        self::assertSame(0, $this->cli('save', '--coverage=var/run.cov', '--junit=var/junit.xml', "--tests-status={$status}", "--status={$status}")[0]);

        return $rc;
    }

    /**
     * Plan; on a resume run the listed files and merge.
     *
     * @return array{0: int, 1: list<string>, 2: int|null, 3: string} plan exit code, files, tests exit code, plan stderr
     */
    private function resume(): array
    {
        [$plan, $out, $err] = $this->cli('plan');
        if (0 !== $plan) {
            return [$plan, [], null, $err];
        }
        $files = array_values(array_filter(explode("\n", $out)));
        file_put_contents($this->root . '/var/resume.xml', str_replace('bootstrap.php', '../bootstrap.php', $this->phpunitXml(implode('', array_map(static fn (string $file): string => "<file>../{$file}</file>", $files)))));
        file_put_contents($this->root . '/var/resume.xml', str_replace(['<directory>src</directory>', 'var/phpunit-cache'], ['<directory>../src</directory>', 'phpunit-cache'], (string) file_get_contents($this->root . '/var/resume.xml')));
        $tests = $this->phpunit('var/resume.xml', 'var/part.cov', 'var/part.xml');
        self::assertSame(0, $this->cli('merge', '--coverage=var/part.cov', '--junit=var/part.xml', '--clover=var/resumed.xml')[0]);

        return [$plan, $files, $tests, $err];
    }

    private function truth(): int
    {
        $process = new Process([\PHP_BINARY, '-d', 'pcov.directory=' . $this->root . '/src', \dirname(__DIR__, 2) . '/vendor/bin/phpunit', '--no-coverage'], $this->root, null, null, 120.0);

        return $process->run();
    }

    private function phpunit(string $config, string $coverage, string $junit, ?string $clover = null): int
    {
        $command = [\PHP_BINARY, '-d', 'pcov.directory=' . $this->root . '/src', \dirname(__DIR__, 2) . '/vendor/bin/phpunit', '-c', $config, '--coverage-php', $coverage, '--log-junit', $junit];
        if (null !== $clover) {
            $command[] = '--coverage-clover';
            $command[] = $clover;
        }

        return (new Process($command, $this->root, null, null, 120.0))->run();
    }

    private function replace(string $path, string $search, string $replace): void
    {
        $content = (string) file_get_contents($this->root . '/' . $path);
        self::assertStringContainsString($search, $content);
        file_put_contents($this->root . '/' . $path, str_replace($search, $replace, $content));
    }

    private function clover(string $file): string
    {
        return (string) preg_replace('/ (generated|timestamp)="\d+"/', '', (string) file_get_contents($this->root . '/' . $file));
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
