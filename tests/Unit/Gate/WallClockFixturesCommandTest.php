<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\WallClock\WallClockFixturesCommand;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class WallClockFixturesCommandTest extends TestCase
{
    use TempTreeTrait;

    private const HINT = ' reads the wall clock (freeze the clock: MockClock / ClockSensitiveTrait::mockTime(), or use a fixed date; @wallclock-ignore to opt out)';

    public function testCleanTreePasses(): void
    {
        $root = $this->makeTree([
            'tests/Unit/FixedTest.php' => "<?php\n\$d = new DateTimeImmutable('2026-01-01');\n",
            'tests/Unit/FrozenTest.php' => "<?php\nuse Symfony\\Component\\Clock\\Test\\ClockSensitiveTrait;\n\$d = new DateTimeImmutable('tomorrow');\n",
            'tests/js/menu.test.ts' => "vi.useFakeTimers();\nconst now = new Date();\n",
            'tests/js/types.d.ts' => "declare const d: Date; // new Date()\n",
            'tests/fixtures/data.json' => '{"at": "new Date()"}',
            'tests/vendor/lib/X.php' => "<?php\ntime();\n",
            'assets/app.ts' => "const now = new Date();\n",
        ]);

        [$code, $out, $err] = $this->runCommand($root, []);

        self::assertSame(0, $code, $err);
        self::assertSame("OK: wall-clock fixtures (3 file(s) scanned, 0 warning(s), 0 baselined file(s)).\n", $out);
    }

    public function testFindingsFail(): void
    {
        $root = $this->makeTree([
            'tests/Unit/ReportTest.php' => "<?php\n\$d = new DateTimeImmutable('tomorrow');\n\$y = date('Y');\n",
            'tests/Fixtures/OrderFixture.php' => "<?php\n\$t = time();\n",
            'assets/controllers/menu_controller.test.ts' => "const now = new Date();\n",
            'assets/controllers/menu_controller.ts' => "const now = new Date();\n",
            'assets/__tests__/clock.js' => "\nconst now = new Date();\n",
        ]);

        [$code, $out, $err] = $this->runCommand($root, ['--paths=tests', '--paths=assets']);

        self::assertSame(1, $code);
        self::assertSame('', $out);
        $js = ' new Date() reads the wall clock (use fake timers: vi.useFakeTimers() / vi.setSystemTime(), jest.useFakeTimers(), or a fixed date)';
        self::assertSame(
            'error: assets/__tests__/clock.js:2' . $js . "\n"
            . 'error: assets/controllers/menu_controller.test.ts:1' . $js . "\n"
            . 'error: tests/Fixtures/OrderFixture.php:2 time()' . self::HINT . "\n"
            . "error: tests/Unit/ReportTest.php:2 new DateTimeImmutable('tomorrow')" . self::HINT . "\n"
            . "error: tests/Unit/ReportTest.php:3 date('Y')" . self::HINT . "\n"
            . "Wall-clock fixtures: 5 error(s), 0 warning(s).\n",
            $err,
        );

        // Adoption mode: the same findings as warnings, exit 0.
        [$code, $out, $err] = $this->runCommand($root, ['--paths=tests', '--paths=assets', '--warn-only']);
        self::assertSame(0, $code);
        self::assertStringContainsString('warning: tests/Fixtures/OrderFixture.php:2 time()', $err);
        self::assertSame("OK: wall-clock fixtures (4 file(s) scanned, 5 warning(s), 0 baselined file(s)).\n", $out);

        // An explicitly listed JS file is scanned even when it is not named like a test; markers and excludes.
        [$code, , $err] = $this->runCommand($root, ['--paths=assets/controllers/menu_controller.ts', '--paths=tests', '--exclude=tests/Unit/', '--frozen-marker=/time\(\)/']);
        self::assertSame(1, $code);
        self::assertSame('error: assets/controllers/menu_controller.ts:1' . $js . "\nWall-clock fixtures: 1 error(s), 0 warning(s).\n", $err);

        [$code] = $this->runCommand($root, ['--exclude=tests/*/OrderFixture.php', '--exclude=./tests/Unit/ReportTest.php']);
        self::assertSame(0, $code);
    }

    public function testBaselineRatchet(): void
    {
        $root = $this->makeTree([
            'tests/OldTest.php' => "<?php\n\$t = time();\n",
            'tests/NewTest.php' => "<?php\n\$d = new DateTime();\n",
            'tests/CleanTest.php' => "<?php\n",
        ]);

        // Write the baseline from the current findings.
        [$code, $out] = $this->runCommand($root, ['--baseline=tests/wallclock-baseline.txt', '--write-baseline']);
        self::assertSame(0, $code);
        self::assertSame('OK: wrote 2 file(s) to the wall-clock baseline ' . $root . "/tests/wallclock-baseline.txt.\n", $out);
        self::assertSame(
            "# check-wallclock-fixtures baseline: files that still read the real clock. Only remove lines.\ntests/NewTest.php\ntests/OldTest.php\n",
            file_get_contents($root . '/tests/wallclock-baseline.txt'),
        );

        // Baselined files only warn.
        [$code, $out, $err] = $this->runCommand($root, ['--baseline=tests/wallclock-baseline.txt']);
        self::assertSame(0, $code, $err);
        self::assertSame(
            'warning: tests/NewTest.php:2 new DateTime()' . self::HINT . " [baseline]\n"
            . 'warning: tests/OldTest.php:2 time()' . self::HINT . " [baseline]\n",
            $err,
        );
        self::assertSame("OK: wall-clock fixtures (3 file(s) scanned, 2 warning(s), 2 baselined file(s)).\n", $out);

        // A new offender fails; a fixed or deleted baselined file must leave the list.
        file_put_contents($root . '/tests/wallclock-baseline.txt', "# legacy\n\n./tests/OldTest.php\ntests/CleanTest.php\ntests/GoneTest.php\n");
        [$code, , $err] = $this->runCommand($root, ['--baseline=./tests/wallclock-baseline.txt']);
        self::assertSame(1, $code);
        self::assertSame(
            'warning: tests/OldTest.php:2 time()' . self::HINT . " [baseline]\n"
            . 'error: tests/NewTest.php:2 new DateTime()' . self::HINT . "\n"
            . "error: baseline entry tests/CleanTest.php has no wall-clock finding any more: remove it from the baseline\n"
            . "error: baseline entry tests/GoneTest.php does not exist or is outside the scanned paths\n"
            . "Wall-clock fixtures: 3 error(s), 1 warning(s).\n",
            $err,
        );

        [$code, , $err] = $this->runCommand($root, ['--baseline=missing.txt']);
        self::assertSame(2, $code);
        self::assertSame("ERROR: baseline file not found or unreadable: missing.txt (create it with --write-baseline)\n", $err);

        [$code, , $err] = $this->runCommand($root, ['--baseline=nodir/baseline.txt', '--write-baseline']);
        self::assertSame(2, $code);
        self::assertSame('ERROR: baseline directory not found: ' . $root . "/nodir\n", $err);
    }

    public function testProjectRootAsPath(): void
    {
        $root = $this->makeTree([
            'tests/Unit/ATest.php' => "<?php\n\$t = time();\n",
            'tests/BTest.php' => "<?php\n\$t = time();\n",
            'vendor/lib/C.php' => "<?php\n\$t = time();\n",
        ]);

        [$code, $out] = $this->runCommand($root, ['--paths=.', '--exclude=tests/Unit/', '--baseline=baseline.txt', '--write-baseline']);
        self::assertSame(0, $code);
        self::assertStringContainsString('wrote 1 file(s)', $out);
        self::assertStringEndsWith("\ntests/BTest.php\n", (string) file_get_contents($root . '/baseline.txt'));

        [$code, $out, $err] = $this->runCommand($root, ['--paths=./', '--exclude=tests/Unit/', '--baseline=baseline.txt']);
        self::assertSame(0, $code, $err);
        self::assertSame("OK: wall-clock fixtures (1 file(s) scanned, 1 warning(s), 1 baselined file(s)).\n", $out);
    }

    public function testUnreadableFilesAndDirectoriesFailClosed(): void
    {
        $root = $this->makeTree([
            'tests/ATest.php' => "<?php\n",
            'tests/BTest.php' => "<?php\n\$t = time();\n",
            'tests/Locked/CTest.php' => "<?php\n\$t = time();\n",
            'baseline.txt' => "tests/BTest.php\n",
        ]);
        chmod($root . '/tests/BTest.php', 0o000);
        if (is_readable($root . '/tests/BTest.php')) {
            chmod($root . '/tests/BTest.php', 0o644);
            self::markTestSkipped('Running as root: permissions cannot make a file unreadable.');
        }

        try {
            [$code, $out, $err] = $this->runCommand($root, ['--exclude=tests/Locked']);
            self::assertSame(2, $code);
            self::assertSame('', $out);
            self::assertSame("ERROR: cannot read tests/BTest.php (not scanned: fix its permissions or --exclude it)\n", $err);

            // --warn-only and --write-baseline do not turn an unreadable file into a pass either.
            [$code] = $this->runCommand($root, ['--exclude=tests/Locked', '--warn-only']);
            self::assertSame(2, $code);
            [$code] = $this->runCommand($root, ['--exclude=tests/Locked', '--baseline=baseline.txt', '--write-baseline']);
            self::assertSame(2, $code);
            self::assertSame("tests/BTest.php\n", file_get_contents($root . '/baseline.txt'));

            chmod($root . '/tests/BTest.php', 0o644);
            chmod($root . '/baseline.txt', 0o000);
            [$code, , $err] = $this->runCommand($root, ['--exclude=tests/Locked', '--baseline=baseline.txt']);
            self::assertSame(2, $code);
            self::assertStringContainsString('ERROR: baseline file not found or unreadable: baseline.txt', $err);

            chmod($root . '/tests/Locked', 0o000);
            [$code, $out, $err] = $this->runCommand($root, []);
            self::assertSame(2, $code);
            self::assertSame('', $out);
            self::assertStringStartsWith('ERROR: cannot read a directory under the scanned paths: ', $err);
            self::assertStringContainsString('tests/Locked', $err);
        } finally {
            chmod($root . '/tests/BTest.php', 0o644);
            chmod($root . '/baseline.txt', 0o644);
            chmod($root . '/tests/Locked', 0o755);
        }
    }

    public function testWriteBaselineStaysInsideTheProject(): void
    {
        $outside = $this->makeTree(['precious.txt' => 'PRECIOUS', 'dir/keep.txt' => 'x']);
        $root = $this->makeTree(['tests/ATest.php' => "<?php\n\$t = time();\n"]);
        symlink($outside . '/precious.txt', $root . '/bl.txt');
        symlink($outside . '/dir', $root . '/linkdir');

        try {
            foreach (['bl.txt', 'linkdir/bl.txt'] as $baseline) {
                [$code, , $err] = $this->runCommand($root, ['--baseline=' . $baseline, '--write-baseline']);
                self::assertSame(2, $code, $baseline);
                self::assertSame("ERROR: refusing to write the baseline {$baseline} through a symlink or outside the project root\n", $err);
            }
            self::assertSame('PRECIOUS', file_get_contents($outside . '/precious.txt'));
            self::assertFileDoesNotExist($outside . '/dir/bl.txt');
        } finally {
            unlink($root . '/bl.txt');
            unlink($root . '/linkdir');
        }

        foreach (['../outside.txt', 'tests/../../x.txt', '.'] as $baseline) {
            [$code, , $err] = $this->runCommand($root, ['--baseline=' . $baseline, '--write-baseline']);
            self::assertSame(2, $code, $baseline);
            self::assertStringContainsString("--baseline must be a file inside the project root (got {$baseline})", $err);
        }
    }

    public function testRootAndMissingDefaultDirectory(): void
    {
        $root = $this->makeTree(['src/A.php' => "<?php\ntime();\n"]);

        [$code, $out] = $this->runCommand(\dirname($root), ['--root=' . basename($root)]);
        self::assertSame(0, $code);
        self::assertSame("OK: wall-clock fixtures (0 file(s) scanned, 0 warning(s), 0 baselined file(s)).\n", $out);

        [$code] = $this->runCommand('/', ['--root=' . $root . '/', '--paths=src/A.php']);
        self::assertSame(1, $code);
    }

    public function testHelpAndUsageErrors(): void
    {
        $root = $this->makeTree(['tests/A.php' => "<?php\n"]);

        [$code, $out] = $this->runCommand($root, ['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: check-wallclock-fixtures', $out);

        foreach ([
            ['--bogus=1', 'Unknown option --bogus=1'],
            ['--paths', 'Unknown or empty option --paths'],
            ['--baseline=', 'Unknown or empty option --baseline='],
            ['--frozen-marker=(unclosed', '--frozen-marker is not a valid PCRE pattern (with delimiters): (unclosed'],
            ['--write-baseline', '--write-baseline needs --baseline=<file>'],
            ['--paths=missing', 'path not found: missing'],
            ['--root=missing', 'root directory not found: ' . $root . '/missing'],
        ] as [$arg, $message]) {
            [$code, , $err] = $this->runCommand($root, [$arg]);
            self::assertSame(2, $code, $arg);
            self::assertStringContainsString($message, $err, $arg);
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
        $code = (new WallClockFixturesCommand($stdout, $stderr, $cwd))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
