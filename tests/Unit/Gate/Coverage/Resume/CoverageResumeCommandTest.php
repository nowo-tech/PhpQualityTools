<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate\Coverage\Resume;

use NowoTech\PhpQualityTools\Gate\Coverage\Resume\CoverageFormat;
use NowoTech\PhpQualityTools\Gate\Coverage\Resume\CoverageResumeCommand;
use NowoTech\PhpQualityTools\Tests\Support\Coverage\CoverageFilesTrait;
use NowoTech\PhpQualityTools\Tests\Unit\Gate\TempTreeTrait;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Version;

/**
 * The plan / merge / save cycle on a small project with chosen per-test data (real php-code-coverage files).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CoverageResumeCommandTest extends TestCase
{
    use CoverageFilesTrait;
    use TempTreeTrait;

    private const CALC = "<?php\nnamespace App;\nclass Calc\n{\n    public function add(int \$a, int \$b): int\n    {\n        return \$a + \$b;\n    }\n\n    public function triple(int \$a): int\n    {\n        return \$a * 3;\n    }\n}\n";
    private const OTHER = "<?php\nnamespace App;\nclass Other\n{\n    public function thrice(int \$a): int\n    {\n        return (new Calc())->triple(\$a);\n    }\n}\n";
    private const TOOLCHAIN = ['php' => '8.2.0', 'phpunit' => '11.5.0', 'codeCoverage' => '11.0.0'];

    private string $root;

    protected function setUp(): void
    {
        $this->root = $this->makeTree([
            'src/Calc.php' => self::CALC,
            'src/Other.php' => self::OTHER,
            'tests/CalcTest.php' => '<?php // tests Calc::add',
            'tests/OtherTest.php' => '<?php // tests Other::thrice',
        ]);
    }

    public function testHelpAndUsageErrors(): void
    {
        self::assertSame([0, CoverageResumeCommand::USAGE . "\n", ''], $this->cli('--help'));
        self::assertSame(2, $this->cli()[0]);
        self::assertStringContainsString('ERROR: Unknown action nope', $this->cli('nope')[2]);
        self::assertStringContainsString('ERROR: Unknown option --clover for plan', $this->cli('plan', '--clover=x')[2]);
        self::assertStringContainsString('ERROR: --max-resumes must be a whole number', $this->cli('plan', '--max-resumes=x')[2]);
        self::assertStringContainsString('save needs --status=passed or --status=failed', $this->cli('save', '--status=green')[2]);
    }

    public function testAFailedFullRunThenASourceFixResumesAndMergesLikeAFullRun(): void
    {
        $calc = $this->root . '/src/Calc.php';
        $other = $this->root . '/src/Other.php';

        // 1. No kept run: full suite.
        [$code, , $err] = $this->cli('plan');
        self::assertSame(3, $code);
        self::assertStringContainsString("full suite —\n  no kept run", $err);
        self::assertSame([0, "coverage: full\n", ''], $this->cli('status'));

        // 2. The full run (OtherTest failed) is kept.
        $this->writeLegacyCoverage($this->root . '/var/run.cov', [
            $calc => [7 => ['App\Tests\CalcTest::testAdd'], 12 => ['App\Tests\OtherTest::testThrice']],
            $other => [7 => ['App\Tests\OtherTest::testThrice']],
        ], ['App\Tests\CalcTest::testAdd' => ['size' => 'unknown', 'status' => 'success'], 'App\Tests\OtherTest::testThrice' => ['size' => 'unknown', 'status' => 'failure']]);
        $this->junit('var/junit.xml', ['App\Tests\CalcTest' => 'tests/CalcTest.php', 'App\Tests\OtherTest' => 'tests/OtherTest.php'], ['tests/OtherTest.php']);
        [$code, $out] = $this->cli('save', '--coverage=var/run.cov', '--junit=var/junit.xml', '--status=failed', '--commit=abc123');
        self::assertSame(0, $code);
        self::assertSame("coverage-resume: kept the full run (failed, 2 test file(s)) as the base of the next resume\n", $out);
        $state = $this->state();
        self::assertSame(['full', 'failed', 0, 0, 'abc123', 'abc123'], [$state['mode'], $state['status'], $state['resumes'], $state['greenResumes'], $state['commit'], $state['fullCommit']]);
        self::assertSame(['src/Calc.php' => [0, 1], 'src/Other.php' => [1]], $state['index']);
        self::assertSame(['tests/OtherTest.php'], $state['failed']);
        self::assertSame(self::TOOLCHAIN + ['driver' => 'unknown'], $state['toolchain']);

        // 3. A body-only fix of Other.php: Other no longer calls Calc::triple().
        file_put_contents($other, str_replace('return (new Calc())->triple($a);', 'return $a * 3;', self::OTHER));
        [$code, $out, $err] = $this->cli('plan');
        self::assertSame(0, $code, $err);
        self::assertSame("tests/OtherTest.php\n", $out);
        self::assertSame("coverage-resume: resumed — 1 test file(s) for 1 changed source file(s): src/Other.php\n", $err);
        self::assertSame([0, "coverage: resumed (1 tests, 1 files)\n", ''], $this->cli('status'));

        // 4. The rerun of OtherTest, merged: Calc line 12 (only OtherTest reached it) is stale and dropped.
        $this->writeLegacyCoverage($this->root . '/var/part.cov', [
            $calc => [7 => [], 12 => []],
            $other => [7 => ['App\Tests\OtherTest::testThrice']],
        ], ['App\Tests\OtherTest::testThrice' => ['size' => 'unknown', 'status' => 'success']]);
        $this->junit('var/part.xml', ['App\Tests\OtherTest' => 'tests/OtherTest.php'], []);
        [$code, $out, $err] = $this->cli('merge', '--coverage=var/part.cov', '--junit=var/part.xml', '--clover=var/clover.xml');
        self::assertSame(0, $code, $err);
        self::assertSame("coverage-resume: merged 1 test file(s) into the kept run, dropped 1 changed source file(s) — lines 2/3 covered\n", $out);
        $merged = (new CoverageFormat())->load($this->root . '/var/coverage/resume/merged.cov')->coverage;
        self::assertSame([7 => ['App\Tests\CalcTest::testAdd' => 1], 12 => []], $merged->lines[$calc]);
        self::assertSame([7 => ['App\Tests\OtherTest::testThrice' => 1]], $merged->lines[$other]);
        self::assertStringContainsString('<line num="12" type="stmt" count="0"/>', (string) file_get_contents($this->root . '/var/clover.xml'));

        // 5. The resumed run passed: kept, with one green resume.
        [$code, $out] = $this->cli('save', '--coverage=var/coverage/resume/merged.cov', '--junit=var/part.xml', '--status=passed');
        self::assertSame(0, $code);
        self::assertSame("coverage-resume: kept the resumed run (passed, 2 test file(s)) as the base of the next resume; 1 green resume(s) since the last full run\n", $out);
        $state = $this->state();
        self::assertSame(['resumed', 'passed', 1, 1, '', 'abc123'], [$state['mode'], $state['status'], $state['resumes'], $state['greenResumes'], $state['commit'], $state['fullCommit']]);
        self::assertSame([], $state['failed']);
        self::assertSame(['App\Tests\CalcTest' => 0, 'App\Tests\OtherTest' => 1], $state['testClasses']);
        self::assertSame('coverage: resumed (1 tests, 1 files)', $state['stamp']);

        // 6. The marker policy: the next run is the full suite.
        file_put_contents($calc, str_replace('$a + $b', '$b + $a', self::CALC));
        [$code, , $err] = $this->cli('plan');
        self::assertSame(3, $code);
        self::assertStringContainsString('1 resumed run(s) already passed since the last full run (--max-green-resumes=1)', $err);

        // 7. A full run resets the counters.
        $this->cli('save', '--coverage=var/run.cov', '--junit=var/junit.xml', '--status=passed');
        self::assertSame(['full', 0, 0], [$this->state()['mode'], $this->state()['resumes'], $this->state()['greenResumes']]);
    }

    public function testNothingIsKeptWhenFilesChangeDuringTheRun(): void
    {
        $this->keepFullRun();
        file_put_contents($this->root . '/src/Other.php', str_replace('triple($a)', 'add($a, $a)', self::OTHER));
        $this->cli('plan');
        file_put_contents($this->root . '/src/Calc.php', self::CALC . "\n");

        [$code, , $err] = $this->cli('save', '--coverage=var/run.cov', '--junit=var/junit.xml', '--status=passed');

        self::assertSame(0, $code);
        self::assertSame("coverage-resume: files changed during the run — nothing kept, the next run is the full suite\n", $err);
        self::assertFileDoesNotExist($this->root . '/var/coverage/resume/state.json');
    }

    public function testMergeAndSaveFailures(): void
    {
        self::assertSame([1, '', "coverage-resume: no plan recorded: run `coverage-resume plan` before the tests\n"], $this->cli('merge'));

        $this->cli('plan');
        self::assertSame([1, '', "coverage-resume: nothing to merge: the last plan was not a resume\n"], $this->cli('merge'));
        self::assertSame([1, '', "coverage-resume: --coverage=<file> is required\n"], $this->cli('save', '--status=failed'));
        self::assertSame([1, '', "coverage-resume: coverage data not found: {$this->root}/none.cov\n"], $this->cli('save', '--status=failed', '--coverage=none.cov', '--junit=x'));

        $this->keepFullRun();
        file_put_contents($this->root . '/tests/OtherTest.php', '<?php // fixed');
        self::assertSame(0, $this->cli('plan')[0]);
        self::assertSame([1, '', "coverage-resume: --junit=<file> is required\n"], $this->cli('merge', '--coverage=var/run.cov'));

        // Another driver, or branch data: the merge refuses and asks for the full suite.
        $this->writeSerializedCoverage($this->root . '/var/serialized.cov', [], []);
        $this->junit('var/part.xml', [], []);
        [$code, , $err] = $this->cli('merge', '--coverage=var/serialized.cov', '--junit=var/part.xml', '--clover=var/c.xml');
        self::assertSame(3, $code);
        self::assertStringContainsString('cannot merge, run the full suite — coverage driver changed (unknown → [{"name":"PHP","version":"8.4.0"},{"name":"pcov"}])', $err);

        $this->writeSerializedCoverage($this->root . '/var/coverage/resume/coverage.cov', [], [], [], '', ['/x.php' => ['f' => []]]);
        $this->writeSerializedCoverage($this->root . '/var/serialized.cov', [], [], [], '', [], true);
        [$code, , $err] = $this->cli('merge', '--coverage=var/serialized.cov', '--junit=var/part.xml', '--clover=var/c.xml');
        self::assertSame(3, $code);
        self::assertStringContainsString('branch / path coverage data cannot be merged', $err);

        // The kept run vanished between the plan and the save of a resume.
        unlink($this->root . '/var/coverage/resume/state.json');
        self::assertSame([1, '', "coverage-resume: the kept run disappeared during the resume\n"], $this->cli('save', '--status=passed', '--coverage=var/run.cov', '--junit=var/junit.xml'));
    }

    public function testAPhptTestIsItsOwnFileAndIsDroppedWhenRerun(): void
    {
        $phpt = $this->root . '/tests/calc.phpt';
        file_put_contents($phpt, '--TEST--');
        $this->cli('plan');
        $this->writeLegacyCoverage($this->root . '/var/run.cov', [$this->root . '/src/Calc.php' => [7 => [$phpt], 12 => [$phpt]], $this->root . '/src/Other.php' => [7 => [$phpt]]]);
        $this->junit('var/junit.xml', [], []);
        self::assertSame(0, $this->cli('save', '--coverage=var/run.cov', '--junit=var/junit.xml', '--status=failed')[0]);
        self::assertSame(['tests/calc.phpt'], $this->state()['testFiles']);
        self::assertSame(['src/Calc.php' => [0], 'src/Other.php' => [0]], $this->state()['index']);

        file_put_contents($this->root . '/src/Calc.php', str_replace('$a * 3', '$a * 3 + 0', self::CALC));
        self::assertSame([0, "tests/calc.phpt\n"], \array_slice($this->cli('plan', '--test-suffix=.phpt'), 0, 2));
        $this->writeLegacyCoverage($this->root . '/var/part.cov', [$this->root . '/src/Calc.php' => [7 => [$phpt], 12 => []], $this->root . '/src/Other.php' => [7 => []]]);
        self::assertSame(0, $this->cli('merge', '--test-suffix=.phpt', '--coverage=var/part.cov', '--junit=var/junit.xml', '--clover=var/c.xml')[0]);

        $merged = (new CoverageFormat())->load($this->root . '/var/coverage/resume/merged.cov')->coverage;
        self::assertSame([7 => [$phpt => 1], 12 => []], $merged->lines[$this->root . '/src/Calc.php']);
        self::assertSame([7 => []], $merged->lines[$this->root . '/src/Other.php'], 'the PHPT test no longer reaches Other.php');
    }

    public function testStatusForgetAndAStateOfAnotherFormat(): void
    {
        self::assertSame([1, '', "coverage-resume: no run planned yet\n"], $this->cli('status'));

        $this->keepFullRun();
        file_put_contents($this->root . '/var/coverage/resume/state.json', '{"format": 0}');
        file_put_contents($this->root . '/tests/OtherTest.php', '<?php // fixed');
        [$code, , $err] = $this->cli('plan');
        self::assertSame(3, $code);
        self::assertStringContainsString('no kept run', $err);

        self::assertSame([0, "coverage-resume: kept state removed\n", ''], $this->cli('forget'));
        self::assertFileDoesNotExist($this->root . '/var/coverage/resume/coverage.cov');
    }

    public function testTheDefaultToolchainReadsTheInstalledVersions(): void
    {
        $toolchain = CoverageResumeCommand::currentToolchain();

        self::assertSame(\PHP_VERSION, $toolchain['php']);
        self::assertSame(Version::id(), $toolchain['phpunit']);
        self::assertSame(\SebastianBergmann\CodeCoverage\Version::id(), $toolchain['codeCoverage']);

        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        self::assertSame(3, (new CoverageResumeCommand($stdout, $stderr, $this->root, ['FORCE_COVERAGE' => '1']))->run(['plan']));
        rewind($stderr);
        self::assertStringContainsString('forced', (string) stream_get_contents($stderr));
    }

    private function keepFullRun(): void
    {
        $this->cli('plan');
        $this->writeLegacyCoverage($this->root . '/var/run.cov', [
            $this->root . '/src/Calc.php' => [7 => ['App\Tests\CalcTest::testAdd'], 12 => ['App\Tests\OtherTest::testThrice']],
            $this->root . '/src/Other.php' => [7 => ['App\Tests\OtherTest::testThrice']],
        ]);
        $this->junit('var/junit.xml', ['App\Tests\CalcTest' => 'tests/CalcTest.php', 'App\Tests\OtherTest' => 'tests/OtherTest.php'], []);
        self::assertSame(0, $this->cli('save', '--coverage=var/run.cov', '--junit=var/junit.xml', '--status=failed')[0]);
    }

    /**
     * @param array<string, string> $classes
     * @param list<string>          $failed
     */
    private function junit(string $path, array $classes, array $failed): void
    {
        $cases = '';
        foreach ($classes as $class => $file) {
            $failure = \in_array($file, $failed, true) ? '<failure>no</failure>' : '';
            $cases .= \sprintf('<testcase name="t" class="%s" file="%s/%s">%s</testcase>', $class, $this->root, $file, $failure);
        }
        if (!is_dir(\dirname($this->root . '/' . $path))) {
            mkdir(\dirname($this->root . '/' . $path), 0o777, true);
        }
        file_put_contents($this->root . '/' . $path, "<?xml version=\"1.0\"?><testsuites><testsuite name=\"all\">{$cases}</testsuite></testsuites>");
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        $state = json_decode((string) file_get_contents($this->root . '/var/coverage/resume/state.json'), true);
        self::assertIsArray($state);

        /* @var array<string, mixed> $state */
        return $state;
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
        $code = (new CoverageResumeCommand($stdout, $stderr, $this->root, [], null, static fn (): array => self::TOOLCHAIN))->run(array_values($args));
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
