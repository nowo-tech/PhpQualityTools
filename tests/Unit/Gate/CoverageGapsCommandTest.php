<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Coverage\CoverageGapsCommand;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CoverageGapsCommandTest extends TestCase
{
    use TempTreeTrait;

    private const CLOVER = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <coverage generated="1">
          <project timestamp="1">
            <package name="App">
              <file name="/app/src/Billing/Invoice.php">
                <line num="10" type="method" name="total" count="0"/>
                <line num="12" type="stmt" count="0"/>
                <line num="13" type="stmt" count="3"/>
                <line num="20" type="stmt" count="0"/>
                <line num="21" type="stmt" count="0"/>
                <line num="22" type="stmt" count="0"/>
                <line num="24" type="cond" count="0"/>
              </file>
            </package>
            <file name="/srv/other/src/Covered.php">
              <line num="5" type="stmt" count="1"/>
            </file>
            <file name="/srv/other/src/Partly.php">
              <line num="9" type="stmt" count="0"/>
              <line num="7" type="stmt" count="0"/>
            </file>
            <metrics files="3" statements="9" coveredstatements="2"/>
          </project>
        </coverage>
        XML;

    private const LCOV = "TN:\nSF:/app/assets/app.ts\nDA:1,1\nDA:2,0\nDA:3,0\nDA:5,0\nend_of_record\n"
        . "SF:/app/assets/full.ts\nDA:1,4\nend_of_record\nSF:assets/broken.ts\nDA:7\nend_of_record\n";

    public function testCloverListsUncoveredStatementsWithCompactRanges(): void
    {
        $root = $this->makeTree(['var/coverage/clover.xml' => self::CLOVER]);

        [$code, $out, $err] = $this->runCommand('/app', [$root . '/var/coverage/clover.xml', '--strip-prefix=/srv/other']);

        self::assertSame(1, $code, $err);
        self::assertSame(
            "coverage-gaps: 2 file(s) below 100 % (uncovered lines):\n"
            . \sprintf("  %-80s %s\n", '/app/src/Billing/Invoice.php', '12, 20-22')
            . \sprintf("  %-80s %s\n", 'src/Partly.php', '7, 9'),
            $out,
        );

        // Default prefix: the current directory.
        [$code, $out] = $this->runCommand('/app', [$root . '/var/coverage/clover.xml']);
        self::assertSame(1, $code);
        self::assertStringContainsString(\sprintf("  %-80s %s\n", 'src/Billing/Invoice.php', '12, 20-22'), $out);
        self::assertStringContainsString('/srv/other/src/Partly.php', $out);
    }

    public function testLcovIsDetectedFromTheContent(): void
    {
        $root = $this->makeTree(['coverage.xml' => self::LCOV]);

        [$code, $out] = $this->runCommand('/app/', ['--strip-prefix=/app/', $root . '/coverage.xml']);

        self::assertSame(1, $code);
        self::assertSame(
            "coverage-gaps: 2 file(s) below 100 % (uncovered lines):\n"
            . \sprintf("  %-80s %s\n", 'assets/app.ts', '2-3, 5')
            . \sprintf("  %-80s %s\n", 'assets/broken.ts', '7'),
            $out,
        );
    }

    public function testFullyCoveredReports(): void
    {
        $root = $this->makeTree([
            'clover.xml' => '<?xml version="1.0"?><coverage><project><file name="a.php"><line num="1" type="stmt" count="2"/></file><metrics/></project></coverage>',
            'lcov.info' => "SF:a.ts\nDA:1,1\nend_of_record\n",
        ]);

        foreach (['clover.xml', 'lcov.info'] as $report) {
            self::assertSame([0, "coverage-gaps: every file is fully covered.\n", ''], $this->runCommand($root, [$report]), $report);
        }
    }

    public function testMissingAndUnreadableReports(): void
    {
        $root = $this->makeTree(['broken.xml' => '<coverage><file']);

        [$code, $out, $err] = $this->runCommand($root, ['missing.xml']);
        self::assertSame([2, '', "ERROR: coverage report not found: missing.xml\n"], [$code, $out, $err]);

        [$code, $out, $err] = $this->runCommand($root, ['broken.xml']);
        self::assertSame([2, '', "ERROR: unreadable Clover report broken.xml\n"], [$code, $out, $err]);
    }

    public function testUsage(): void
    {
        [$code, $out] = $this->runCommand('/', ['-h']);
        self::assertSame([0, CoverageGapsCommand::USAGE . "\n"], [$code, $out]);

        foreach ([[], ['a.xml', 'b.xml'], ['--strip-prefix=', 'a.xml'], ['--nope', 'a.xml']] as $args) {
            [$code, $out, $err] = $this->runCommand('/', $args);
            self::assertSame(2, $code, implode(' ', $args));
            self::assertSame('', $out);
            self::assertStringStartsWith('ERROR: ', $err);
            self::assertStringContainsString('Usage: coverage-gaps', $err);
        }
    }

    public function testCompactRanges(): void
    {
        self::assertSame([], CoverageGapsCommand::compactRanges([]));
        self::assertSame(['1-3', '5', '8-9'], CoverageGapsCommand::compactRanges([9, 2, 1, 3, 5, 8, 2]));
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
        $code = (new CoverageGapsCommand($stdout, $stderr, $cwd))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
