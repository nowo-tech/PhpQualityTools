<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing\Pid;

use NowoTech\PhpQualityTools\Testing\Pid\StalePidPathsCommand;
use NowoTech\PhpQualityTools\Tests\Unit\Gate\TempTreeTrait;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class StalePidPathsCommandTest extends TestCase
{
    use TempTreeTrait;

    private const ALIVE = [100, 300];

    public function testRemovesOnlyPathsOfDeadProcesses(): void
    {
        $root = $this->makeTree([
            'app-phpunit-100.db' => 'alive',
            'app-phpunit-200.db' => 'dead',
            'app-phpunit-200.db-wal' => 'dead',
            'app-phpunit-x.db' => 'no pid',
            'app-markers-200/setup.done' => '',
            'app-markers-200/nested/deep' => '',
            'app-markers-300/setup.done' => '',
            'app-markers-0/setup.done' => '',
            'other-200' => 'not matched',
        ]);

        [$code, $out, $err] = $this->runCommand([$root . '/app-phpunit-{pid}.db*', $root . '/app-markers-{pid}']);

        self::assertSame(0, $code, $err);
        self::assertSame("clean-stale-pid-paths: removed 3 path(s) of dead processes, kept 2 of running processes.\n", $out);
        self::assertFileExists($root . '/app-phpunit-100.db');
        self::assertFileDoesNotExist($root . '/app-phpunit-200.db');
        self::assertFileDoesNotExist($root . '/app-phpunit-200.db-wal');
        self::assertFileExists($root . '/app-phpunit-x.db');
        self::assertDirectoryDoesNotExist($root . '/app-markers-200');
        self::assertFileExists($root . '/app-markers-300/setup.done');
        self::assertFileExists($root . '/app-markers-0/setup.done');
        self::assertFileExists($root . '/other-200');
    }

    public function testDryRunListsAndKeepsEverything(): void
    {
        $root = $this->makeTree(['m-200/a' => '', 'm-100/a' => '']);

        [$code, $out] = $this->runCommand(['--dry-run', '--quiet', $root . '/m-{pid}']);

        self::assertSame(0, $code);
        self::assertStringContainsString("Would remove {$root}/m-200 (process 200 is gone)\n", $out);
        self::assertStringContainsString('would remove 1 path(s) of dead processes, kept 1 of running processes.', $out);
        self::assertDirectoryExists($root . '/m-200');
    }

    public function testQuietPrintsNothingAndUnlinksSymlinksWithoutFollowingThem(): void
    {
        $root = $this->makeTree(['target/keep' => 'x', 'q-200.tmp' => '']);
        symlink($root . '/target', $root . '/link-200');

        [$code, $out] = $this->runCommand(['-q', $root . '/q-{pid}.tm?', $root . '/link-{pid}']);

        self::assertSame(0, $code);
        self::assertSame('', $out);
        self::assertFileDoesNotExist($root . '/q-200.tmp');
        self::assertFalse(is_link($root . '/link-200'));
        self::assertFileExists($root . '/target/keep');
    }

    public function testGlobMetacharactersInTheLiteralPartAreEscaped(): void
    {
        $root = $this->makeTree(['br[a]-200' => '', 'bra-200' => '']);

        [$code] = $this->runCommand([$root . '/br[a]-{pid}']);

        self::assertSame(0, $code);
        self::assertFileDoesNotExist($root . '/br[a]-200');
        self::assertFileExists($root . '/bra-200');
    }

    public function testReportsPathsThatCannotBeRemoved(): void
    {
        $root = $this->makeTree(['gone-200' => '']);
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        // The path vanishes between the match and the removal (another cleaner won the race): unlink fails.
        $isAlive = static function () use ($root): bool {
            unlink($root . '/gone-200');

            return false;
        };

        self::assertSame(1, (new StalePidPathsCommand($stdout, $stderr, $isAlive))->run([$root . '/gone-{pid}']));
        rewind($stderr);
        self::assertSame("ERROR: could not remove {$root}/gone-200\n", stream_get_contents($stderr));
    }

    public function testNothingMatchedIsOk(): void
    {
        [$code, $out] = $this->runCommand([sys_get_temp_dir() . '/pqt-none-' . bin2hex(random_bytes(4)) . '-{pid}']);

        self::assertSame(0, $code);
        self::assertStringContainsString('removed 0 path(s)', $out);
    }

    public function testHelpAndUsageErrors(): void
    {
        [$code, $out] = $this->runCommand(['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: clean-stale-pid-paths', $out);

        [$code, , $err] = $this->runCommand([]);
        self::assertSame(2, $code);
        self::assertStringContainsString('ERROR: Expected at least one <pattern>.', $err);

        [$code, , $err] = $this->runCommand(['--force', '/tmp/x-{pid}']);
        self::assertSame(2, $code);
        self::assertStringContainsString('ERROR: Unknown option --force', $err);

        [$code, , $err] = $this->runCommand(['/tmp/no-placeholder']);
        self::assertSame(2, $code);
        self::assertStringContainsString('exactly one {pid} placeholder: /tmp/no-placeholder', $err);

        [$code] = $this->runCommand(['/tmp/{pid}-{pid}']);
        self::assertSame(2, $code);
    }

    public function testDefaultLivenessCheck(): void
    {
        $pid = (int) getmypid();
        $noProc = $this->makeTree(['empty' => '']);

        if (is_dir('/proc/self')) {
            self::assertTrue(StalePidPathsCommand::isProcessAlive($pid));
            self::assertFalse(StalePidPathsCommand::isProcessAlive(999_999_999));
        }
        if (\function_exists('posix_kill')) {
            self::assertTrue(StalePidPathsCommand::isProcessAlive($pid, $noProc));
            self::assertFalse(StalePidPathsCommand::isProcessAlive(999_999_999, $noProc));
        }
        // Neither procfs nor posix: assume alive, never remove.
        self::assertTrue(StalePidPathsCommand::isProcessAlive(999_999_999, $noProc, false));

        // The default checker is used when none is injected: our own directory is kept.
        $root = $this->makeTree(['own-' . $pid . '/a' => '']);
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        self::assertSame(0, (new StalePidPathsCommand($stdout, $stderr))->run([$root . '/own-{pid}']));
        self::assertDirectoryExists($root . '/own-' . $pid);
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string, string}
     */
    private function runCommand(array $args): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        $code = (new StalePidPathsCommand($stdout, $stderr, static fn (int $pid): bool => \in_array($pid, self::ALIVE, true)))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
