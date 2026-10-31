<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Junit\SlowestTestsCommand;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class SlowestTestsCommandTest extends TestCase
{
    use TempTreeTrait;

    private const JUNIT = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <testsuites>
          <testsuite name="default">
            <testsuite name="App\Tests\ATest" file="/app/tests/ATest.php">
              <testcase name="a1" class="App\Tests\ATest" file="/app/tests/ATest.php" time="1.5"/>
              <testcase name="a2" class="App\Tests\ATest" file="/app/tests/ATest.php" time="0.5"/>
            </testsuite>
            <testcase name="b1" class="App\Tests\BTest" file="/srv/tests/BTest.php" time="6.0"/>
            <testcase name="c1" class="App\Tests\CTest" time="2"/>
            <testcase name="bare"/>
          </testsuite>
        </testsuites>
        XML;

    public function testSumsTimesPerFileSlowestFirst(): void
    {
        $root = $this->makeTree(['var/coverage/junit.xml' => self::JUNIT]);

        [$code, $out, $err] = $this->runCommand('/app', [$root . '/var/coverage/junit.xml', '--strip-prefix=/srv/', '--strip-prefix=/app']);

        self::assertSame(0, $code, $err);
        self::assertSame(
            "Slowest test files (4 files, 10.0s of test time in total):\n"
            . "      6.0s  60.0%  tests/BTest.php\n"
            . "      2.0s  20.0%  tests/ATest.php\n"
            . "      2.0s  20.0%  App\\Tests\\CTest\n"
            . "      0.0s   0.0%  ?\n",
            $out,
        );

        // Default log path and the current directory as prefix; limit as argument or option.
        [$code, $out] = $this->runCommand($root, []);
        self::assertSame(0, $code);
        self::assertStringContainsString('/srv/tests/BTest.php', $out);
        [, $positional] = $this->runCommand('/app', [$root . '/var/coverage/junit.xml', '1']);
        [, $option] = $this->runCommand('/app', ['--limit=1', $root . '/var/coverage/junit.xml']);
        self::assertSame("Slowest test files (4 files, 10.0s of test time in total):\n      6.0s  60.0%  /srv/tests/BTest.php\n", $positional);
        self::assertSame($positional, $option);
    }

    public function testEmptyLog(): void
    {
        $root = $this->makeTree(['junit.xml' => '<testsuites/>']);

        self::assertSame([0, "Slowest test files (0 files, 0.0s of test time in total):\n", ''], $this->runCommand($root, ['junit.xml']));
    }

    public function testMissingAndUnreadableLogs(): void
    {
        $root = $this->makeTree(['broken.xml' => '<testsuites><testcase']);

        self::assertSame([2, '', "ERROR: no JUnit log at missing.xml\n"], $this->runCommand($root, ['missing.xml']));
        self::assertSame([2, '', "ERROR: unreadable JUnit log broken.xml\n"], $this->runCommand($root, ['broken.xml']));
    }

    public function testUsage(): void
    {
        [$code, $out] = $this->runCommand('/', ['--help']);
        self::assertSame([0, SlowestTestsCommand::USAGE . "\n"], [$code, $out]);

        foreach ([['a.xml', '1', '2'], ['a.xml', '0'], ['--limit=x', 'a.xml'], ['--limit', 'a.xml'], ['--nope=1']] as $args) {
            [$code, $out, $err] = $this->runCommand('/', $args);
            self::assertSame(2, $code, implode(' ', $args));
            self::assertSame('', $out);
            self::assertStringStartsWith('ERROR: ', $err);
            self::assertStringContainsString('Usage: slowest-tests', $err);
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
        $code = (new SlowestTestsCommand($stdout, $stderr, $cwd))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
