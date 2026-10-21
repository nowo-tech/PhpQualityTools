<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Junit\JunitFailuresCommand;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class JunitFailuresCommandTest extends TestCase
{
    use TempTreeTrait;

    private const PASSING = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <testsuites>
          <testsuite name="default" tests="2" failures="0" errors="0">
            <testcase name="testA" class="App\Tests\ATest" file="/app/tests/ATest.php" line="10"/>
            <testcase name="testB" class="App\Tests\ATest" file="/app/tests/ATest.php" line="20"><skipped/></testcase>
          </testsuite>
        </testsuites>
        XML;

    private const FAILING = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <testsuites>
          <testsuite name="default">
            <testsuite name="App\Tests\ATest" file="/app/tests/ATest.php">
              <testcase name="testOk" class="App\Tests\ATest" file="/app/tests/ATest.php" line="10"/>
              <testcase name="testFails" class="App\Tests\ATest" file="/app/tests/ATest.php" line="20">
                <failure type="PHPUnit\Framework\ExpectationFailedException">App\Tests\ATest::testFails
        Failed asserting that false is true.

        /app/tests/ATest.php:22</failure>
              </testcase>
            </testsuite>
            <testcase name="testErrors" class="App\Tests\BTest" file="/srv/project/tests/BTest.php" line="7">
              <error type="RuntimeException">App\Tests\BTest::testErrors
        RuntimeException: boom
        line 3
        line 4</error>
            </testcase>
            <testcase name="testNoLocation"><error>bare</error></testcase>
          </testsuite>
        </testsuites>
        XML;

    public function testPassingLogPrintsNothing(): void
    {
        $root = $this->makeTree(['junit.xml' => self::PASSING]);

        self::assertSame([0, '', ''], $this->runCommand($root, ['junit.xml']));
    }

    public function testListsFailuresAndErrors(): void
    {
        $root = $this->makeTree(['var/junit.xml' => self::FAILING]);

        [$code, $out, $err] = $this->runCommand('/app', [$root . '/var/junit.xml', '--strip-prefix=/app', '--strip-prefix=/srv/project/']);

        self::assertSame(1, $code, $err);
        self::assertSame(
            'Failed tests in ' . $root . "/var/junit.xml (3):\n"
            . "  App\\Tests\\ATest::testFails (tests/ATest.php:20)\n"
            . "      App\\Tests\\ATest::testFails\n"
            . "      Failed asserting that false is true.\n"
            . "      /app/tests/ATest.php:22\n"
            . "  App\\Tests\\BTest::testErrors (tests/BTest.php:7)\n"
            . "      App\\Tests\\BTest::testErrors\n"
            . "      RuntimeException: boom\n"
            . "      line 3\n"
            . "      line 4\n"
            . "  testNoLocation\n"
            . "      bare\n",
            $out,
        );
    }

    public function testLimitsTestsAndLinesAndStripsTheCurrentDirectory(): void
    {
        $root = $this->makeTree([]);
        file_put_contents($root . '/junit.xml', str_replace(['/app/', '/srv/project/'], $root . '/', self::FAILING));

        [$code, $out] = $this->runCommand($root, ['junit.xml', '1', '2']);
        self::assertSame(1, $code);
        self::assertSame(
            "Failed tests in junit.xml (3):\n"
            . "  App\\Tests\\ATest::testFails (tests/ATest.php:20)\n"
            . "      App\\Tests\\ATest::testFails\n"
            . "      Failed asserting that false is true.\n"
            . "  … and 2 more (see the JUnit log).\n",
            $out,
        );

        [$code, $flags] = $this->runCommand($root, ['--max=1', '--lines=2', 'junit.xml']);
        self::assertSame(1, $code);
        self::assertSame($out, $flags);
    }

    public function testMissingAndUnreadableLogs(): void
    {
        $root = $this->makeTree(['broken.xml' => '<testsuites><testcase']);

        [$code, $out, $err] = $this->runCommand($root, ['missing.xml']);
        self::assertSame([2, ''], [$code, $out]);
        self::assertSame("ERROR: no JUnit log at missing.xml\n", $err);

        [$code, , $err] = $this->runCommand($root, ['broken.xml']);
        self::assertSame(2, $code);
        self::assertSame("ERROR: unreadable JUnit log broken.xml\n", $err);
    }

    public function testUsage(): void
    {
        [$code, $out] = $this->runCommand('/', ['--help']);
        self::assertSame(0, $code);
        self::assertSame(JunitFailuresCommand::USAGE . "\n", $out);

        foreach ([[], ['a.xml', '1', '2', '3'], ['a.xml', '0'], ['a.xml', '--lines=x'], ['--max', 'a.xml'], ['--nope=1', 'a.xml']] as $args) {
            [$code, $out, $err] = $this->runCommand('/', $args);
            self::assertSame(2, $code, implode(' ', $args));
            self::assertSame('', $out);
            self::assertStringStartsWith('ERROR: ', $err);
            self::assertStringContainsString('Usage: junit-failures', $err);
        }
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string, string}
     */
    private function runCommand(string $cwd, array $args): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        $code = (new JunitFailuresCommand($stdout, $stderr, $cwd))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
