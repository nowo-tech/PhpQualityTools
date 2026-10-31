<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * bin/heavy-lock, bin/release-phase, bin/memory-guard, bin/check-changed-src-tested and the heavy.mk, quiet.mk and
 * release-phases.mk snippets, in temporary directories (lock and queue directories included: never the machine's).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class DevToolingScriptsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private string $dir;

    /** @var array<string, string|bool> */
    private array $env;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pqt-devtools-' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->dir);
        $this->env = [
            'DOCKER_HEAVY_LOCK_DIR' => $this->dir . '/heavy.lock',
            'DOCKER_HEAVY_QUEUE_DIR' => $this->dir . '/heavy.queue',
            'DOCKER_HEAVY_STALE_LOG' => $this->dir . '/heavy.stale.log',
            'DOCKER_HEAVY_MAXLOAD' => '100000', 'DOCKER_HEAVY_POLL' => '1', 'DOCKER_HEAVY_LOCK_MAXAGE' => false,
            'DOCKER_HEAVY_PRIORITY' => false, 'DOCKER_HEAVY_LOCK_PID' => false, 'DOCKER_HEAVY_LOCK_HELD' => false,
            'MAXLOAD' => false, 'POLL' => false, 'CI' => false, 'MAKEFLAGS' => false, 'MFLAGS' => false, 'MAKELEVEL' => false,
            'RELEASE_PHASE_DIR' => false, 'RELEASE_PHASE_TITLE' => false,
            'MEMGUARD' => false, 'MEMGUARD_MIN_MB' => false, 'MEMGUARD_INTERVAL' => false, 'MEMGUARD_MEMINFO' => false,
            'COVERAGE_HINT_BASE' => false, 'BASE' => false, 'CHANGED_TESTS_SRC' => false, 'CHANGED_TESTS_DIRS' => false,
            'QUIET_JUNIT_CMD' => false, 'QUIET_JUNIT_DIRS' => false,
            'GIT_CEILING_DIRECTORIES' => \dirname($this->dir),
        ];
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testHeavyLockAcquireStatusRelease(): void
    {
        self::assertSame([0, "free\n"], $this->firstLine($this->bin('heavy-lock', ['status'])));

        $process = $this->bin('heavy-lock', ['acquire', 'app: test'], ['DOCKER_HEAVY_LOCK_PID' => (string) getmypid()]);
        self::assertSame([0, "acquired by app: test\n"], [$process->getExitCode(), $process->getOutput()]);
        self::assertSame(['app: test', (string) getmypid()], [
            explode("\n", (string) file_get_contents($this->dir . '/heavy.lock/owner'))[0],
            explode("\n", (string) file_get_contents($this->dir . '/heavy.lock/owner'))[2],
        ]);
        $status = $this->bin('heavy-lock', ['status'])->getOutput();
        self::assertMatchesRegularExpression('#^held:\napp: test\n\d{4}-\d\d-\d\dT[\d:]+Z\npid ' . getmypid() . "\nload: #", $status);
        self::assertSame([], glob($this->dir . '/heavy.queue/*'));

        $process = $this->bin('heavy-lock', ['release']);
        self::assertSame([0, "released\n"], [$process->getExitCode(), $process->getOutput()]);
        self::assertDirectoryDoesNotExist($this->dir . '/heavy.lock');
    }

    public function testHeavyLockRunKeepsTheExitCodeReleasesAndIsReentrant(): void
    {
        $script = 'echo "held=$DOCKER_HEAVY_LOCK_HELD"; test -d "$DOCKER_HEAVY_LOCK_DIR" && echo locked; '
            . 'sh ' . escapeshellarg(self::ROOT . '/bin/heavy-lock') . ' run inner sh -c "echo inner"; '
            . 'sh ' . escapeshellarg(self::ROOT . '/bin/heavy-lock') . ' release; exit 5';
        $process = $this->bin('heavy-lock', ['run', 'app: suite', 'sh', '-c', $script]);

        self::assertSame(5, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame("acquired by app: suite\nheld=1\nlocked\ninner\nlock already held by an outer run (DOCKER_HEAVY_LOCK_HELD)\n", $process->getOutput());
        self::assertDirectoryDoesNotExist($this->dir . '/heavy.lock');

        $process = $this->bin('heavy-lock', ['acquire', 'x'], ['DOCKER_HEAVY_LOCK_HELD' => '1']);
        self::assertSame([0, "lock already held by an outer run (DOCKER_HEAVY_LOCK_HELD)\n"], [$process->getExitCode(), $process->getOutput()]);
    }

    public function testHeavyLockWaitsForTheHolderAndTheLoadAndRemovesStaleHolders(): void
    {
        // A live holder: the waiter queues (ticket visible in status) until the lock is released.
        (new Filesystem())->mkdir($this->dir . '/heavy.lock');
        file_put_contents($this->dir . '/heavy.lock/owner', "other: e2e\n2026-01-01T00:00:00Z\n" . getmypid() . "\n" . time() . "\n");
        $waiter = $this->start(['sh', self::ROOT . '/bin/heavy-lock', 'acquire', 'app: waiter']);
        $this->waitFor(fn (): bool => [] !== glob($this->dir . '/heavy.queue/*'));
        usleep(1_200_000);
        self::assertTrue($waiter->isRunning());
        self::assertStringContainsString("queue (oldest first):\n  app: waiter [", $this->bin('heavy-lock', ['status'])->getOutput());
        $this->bin('heavy-lock', ['release']);
        $waiter->wait();
        self::assertSame([0, "acquired by app: waiter\n"], [$waiter->getExitCode(), $waiter->getOutput()]);
        self::assertSame([], glob($this->dir . '/heavy.queue/*'));
        $this->bin('heavy-lock', ['release']);

        // Load above the threshold: no lock is taken.
        $waiter = $this->start(['sh', self::ROOT . '/bin/heavy-lock', 'acquire', 'app: load'], ['DOCKER_HEAVY_MAXLOAD' => '0']);
        usleep(1_500_000);
        self::assertTrue($waiter->isRunning());
        self::assertDirectoryDoesNotExist($this->dir . '/heavy.lock');
        $waiter->stop(1);

        // A holder whose PID is gone, and one without PID older than the maximum age, are removed and logged.
        foreach (["gone: job\n2026-01-01T00:00:00Z\n" . $this->deadPid() . "\n" . time() . "\n", "old: job\n2026-01-01T00:00:00Z\n-\n" . (time() - 7200) . "\n"] as $owner) {
            (new Filesystem())->mkdir($this->dir . '/heavy.lock');
            file_put_contents($this->dir . '/heavy.lock/owner', $owner);
            self::assertStringContainsString('(stale: ', $this->bin('heavy-lock', ['status'])->getOutput());
            $process = $this->bin('heavy-lock', ['acquire', 'app: next']);
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertMatchesRegularExpression('#stale lock of "(gone|old): job" removed by "app: next" \((pid \d+ gone|held 120 min without a live PID)\)#', $process->getErrorOutput());
            $this->bin('heavy-lock', ['release']);
        }
        self::assertCount(2, file($this->dir . '/heavy.stale.log') ?: []);

        // A recent holder without PID is not stale.
        (new Filesystem())->mkdir($this->dir . '/heavy.lock');
        file_put_contents($this->dir . '/heavy.lock/owner', "recent: job\n2026-01-01T00:00:00Z\n-\n" . time() . "\n");
        self::assertStringContainsString("pid -\n", $this->bin('heavy-lock', ['status'])->getOutput());
    }

    public function testHeavyLockFifoAndPriorityTickets(): void
    {
        (new Filesystem())->mkdir([$this->dir . '/heavy.lock', $this->dir . '/heavy.queue']);
        file_put_contents($this->dir . '/heavy.lock/owner', "holder\n2026-01-01T00:00:00Z\n" . getmypid() . "\n" . time() . "\n");
        // A dead waiter's ticket is pruned; a priority ticket sorts before normal ones.
        $deadTicket = $this->dir . '/heavy.queue/1-' . $this->deadPid();
        file_put_contents($deadTicket, "dead\n");
        $normal = $this->start(['sh', self::ROOT . '/bin/heavy-lock', 'acquire', 'normal']);
        $this->waitFor(fn (): bool => !is_file($deadTicket) && 1 === \count(glob($this->dir . '/heavy.queue/*') ?: []));
        $priority = $this->start(['sh', self::ROOT . '/bin/heavy-lock', 'acquire', 'priority'], ['DOCKER_HEAVY_PRIORITY' => '1']);
        $this->waitFor(fn (): bool => [] !== glob($this->dir . '/heavy.queue/0-*'));
        $status = $this->bin('heavy-lock', ['status'])->getOutput();
        self::assertMatchesRegularExpression("#queue \\(oldest first\\):\n  priority \\[0-\\d+-\\d+\\]\n  normal \\[\\d+-\\d+\\]\n#", $status);

        $this->bin('heavy-lock', ['release']);
        $priority->wait();
        self::assertSame("acquired by priority\n", $priority->getOutput());
        usleep(1_200_000);
        self::assertTrue($normal->isRunning());
        $this->bin('heavy-lock', ['release']);
        $normal->wait();
        self::assertSame("acquired by normal\n", $normal->getOutput());
        $this->bin('heavy-lock', ['release']);
    }

    public function testHeavyLockUsage(): void
    {
        $process = $this->bin('heavy-lock', ['--help']);
        self::assertSame(0, $process->getExitCode());
        self::assertStringContainsString('Usage: heavy-lock acquire <owner>', $process->getOutput());

        foreach ([[], ['nope'], ['acquire'], ['run', 'owner']] as $args) {
            $process = $this->bin('heavy-lock', $args);
            self::assertSame(2, $process->getExitCode(), implode(' ', $args));
            self::assertStringStartsWith('ERROR: ', $process->getErrorOutput());
        }
        $process = $this->bin('heavy-lock', ['run', 'owner'], ['DOCKER_HEAVY_LOCK_HELD' => '1']);
        self::assertSame(2, $process->getExitCode());
        $process = $this->bin('heavy-lock', ['run', 'owner', 'sh', '-c', 'exit 3'], ['DOCKER_HEAVY_LOCK_HELD' => '1']);
        self::assertSame([3, ''], [$process->getExitCode(), $process->getOutput()]);
    }

    public function testReleasePhaseLogsEachPhaseAndNamesTheFailingOne(): void
    {
        self::assertSame(0, $this->bin('release-phase', ['--reset'])->getExitCode());
        $process = $this->bin('release-phase', ['lint', 'sh', '-c', 'echo linted; echo warn >&2']);
        self::assertSame([0, "linted\nwarn\n"], [$process->getExitCode(), $process->getOutput()]);
        self::assertSame("linted\nwarn\n", file_get_contents($this->dir . '/var/quiet/release/lint.log'));

        $process = $this->bin('release-phase', ['php-coverage', 'sh', '-c', 'echo boom; exit 4']);
        self::assertSame(4, $process->getExitCode());
        self::assertMatchesRegularExpression("#^boom\n\nrelease-check phases:\n  OK   lint \\d+s\n  FAIL php-coverage \\d+s — log: var/quiet/release/php-coverage\\.log\n$#", $process->getOutput());

        $process = $this->bin('release-phase', ['--summary', 'precheck']);
        self::assertMatchesRegularExpression("#^precheck phases:\n  OK   lint \\d+s\n  FAIL php-coverage#", $process->getOutput());

        // Own directory and title; a missing command keeps the shell's exit code; --reset empties the summary.
        $env = ['RELEASE_PHASE_DIR' => 'build/phases', 'RELEASE_PHASE_TITLE' => 'gate'];
        $process = $this->bin('release-phase', ['missing', 'pqt-no-such-command-xyz'], $env);
        self::assertSame(127, $process->getExitCode());
        self::assertStringContainsString("gate phases:\n  FAIL missing", $process->getOutput());
        $this->bin('release-phase', ['--reset'], $env);
        self::assertSame("gate phases:\n", $this->bin('release-phase', ['--summary'], $env)->getOutput());
    }

    public function testReleasePhaseUsage(): void
    {
        $process = $this->bin('release-phase', ['-h']);
        self::assertSame(0, $process->getExitCode());
        self::assertStringContainsString('Usage: release-phase <phase> <command>', $process->getOutput());

        foreach ([[], ['only-phase'], ['--bogus'], ['bad/name', 'true']] as $args) {
            $process = $this->bin('release-phase', $args);
            self::assertSame(2, $process->getExitCode(), implode(' ', $args));
            self::assertStringStartsWith('ERROR: ', $process->getErrorOutput());
        }
    }

    public function testMemoryGuardStopsTheSuiteBelowTheMinimum(): void
    {
        file_put_contents($this->dir . '/meminfo', "MemTotal: 16000000 kB\nMemAvailable: 1048576 kB\n");
        $env = ['MEMGUARD_MEMINFO' => $this->dir . '/meminfo', 'MEMGUARD_INTERVAL' => '1'];

        $process = $this->bin('memory-guard', ['--kill', 'touch killed', '--', 'sleep', '30'], $env);
        self::assertNotSame(0, $process->getExitCode());
        self::assertStringContainsString('[memory-guard] load ', $process->getErrorOutput());
        self::assertStringContainsString('MemAvailable 1024 MB < 4096 MB: stopping the suite.', $process->getErrorOutput());
        self::assertFileExists($this->dir . '/killed');

        // Enough memory: the suite's exit code and output.
        $process = $this->bin('memory-guard', ['--kill=touch killed2', 'sh', '-c', 'echo ran; exit 6'], [...$env, 'MEMGUARD_MIN_MB' => '512']);
        self::assertSame([6, "ran\n"], [$process->getExitCode(), $process->getOutput()]);
        self::assertFileDoesNotExist($this->dir . '/killed2');

        // Unguarded: MEMGUARD=0 or no meminfo file.
        foreach ([['MEMGUARD' => '0'], ['MEMGUARD_MEMINFO' => $this->dir . '/none']] as $unguarded) {
            $process = $this->bin('memory-guard', ['sh', '-c', 'echo plain; exit 3'], [...$env, ...$unguarded]);
            self::assertSame([3, "plain\n", ''], [$process->getExitCode(), $process->getOutput(), $process->getErrorOutput()]);
        }
    }

    public function testMemoryGuardUsage(): void
    {
        $process = $this->bin('memory-guard', ['--help']);
        self::assertSame(0, $process->getExitCode());
        self::assertStringContainsString("Usage: memory-guard [--kill '<command>']", $process->getOutput());

        file_put_contents($this->dir . '/meminfo', "MemAvailable: 1 kB\n");
        foreach ([[[], []], [['--kill'], []], [['--'], []], [['true'], ['MEMGUARD_MEMINFO' => $this->dir . '/meminfo', 'MEMGUARD_MIN_MB' => 'x']]] as [$args, $env]) {
            $process = $this->bin('memory-guard', $args, $env);
            self::assertSame(2, $process->getExitCode(), implode(' ', $args));
            self::assertStringStartsWith('ERROR: ', $process->getErrorOutput());
        }
    }

    public function testCheckChangedSrcTestedListsUntestedClasses(): void
    {
        // Outside a repository, or without a tag: nothing to compare.
        self::assertSame([0, ''], $this->outcome($this->bin('check-changed-src-tested', [])));
        $this->git('init', '-q', '-b', 'main');
        $this->dump(['src/Billing/Invoice.php' => "<?php\n", 'src/Billing/Refund.php' => "<?php\n", 'tests/InvoiceTest.php' => "<?php\n// Invoice\n"]);
        $this->git('add', '.');
        $this->git('commit', '-qm', 'init');
        self::assertSame([0, ''], $this->outcome($this->bin('check-changed-src-tested', [])));
        $this->git('tag', 'v1.0.0');

        // Committed changes since the tag only (the working tree is ignored); deleted files are ignored.
        $this->dump(['src/Billing/Invoice.php' => "<?php\n// v2\n", 'src/Billing/Credit.php' => "<?php\n", 'src/Billing/CreditTest.txt' => "x\n", 'lib/Mailer.php' => "<?php\n", 'tests/Unit/CreditNoteTest.php' => "<?php\n// CreditNote only\n"]);
        $this->git('rm', '-q', 'src/Billing/Refund.php');
        $this->git('add', '.');
        $this->git('commit', '-qm', 'feat');
        $this->dump(['src/Billing/Draft.php' => "<?php\n"]);

        $hint = "Coverage hint: source classes changed since v1.0.0 that no test names (check them first if the coverage gate fails):\n  src/Billing/Credit.php\n";
        self::assertSame([0, $hint], $this->outcome($this->bin('check-changed-src-tested', [])));
        self::assertSame([1, $hint], $this->outcome($this->bin('check-changed-src-tested', ['--strict'])));

        // Options and environment: base, source and test directories.
        self::assertSame([0, ''], $this->outcome($this->bin('check-changed-src-tested', ['--base=HEAD'])));
        $lib = "Coverage hint: source classes changed since v1.0.0 that no test names (check them first if the coverage gate fails):\n  lib/Mailer.php\n";
        self::assertSame([0, $lib], $this->outcome($this->bin('check-changed-src-tested', ['--src', 'lib', '--tests=tests/'])));
        self::assertSame([0, $lib], $this->outcome($this->bin('check-changed-src-tested', [], ['COVERAGE_HINT_BASE' => 'v1.0.0', 'CHANGED_TESTS_SRC' => 'lib'])));
        $both = "Coverage hint: source classes changed since v1.0.0 that no test names (check them first if the coverage gate fails):\n  src/Billing/Credit.php\n  src/Billing/Invoice.php\n";
        self::assertSame([0, $both], $this->outcome($this->bin('check-changed-src-tested', [], ['BASE' => 'v1.0.0', 'CHANGED_TESTS_DIRS' => 'missing-dir'])));
    }

    public function testCheckChangedSrcTestedUsage(): void
    {
        $process = $this->bin('check-changed-src-tested', ['--help']);
        self::assertSame(0, $process->getExitCode());
        self::assertStringContainsString('Usage: check-changed-src-tested [options]', $process->getOutput());

        $this->git('init', '-q', '-b', 'main');
        $this->git('commit', '-q', '--allow-empty', '-m', 'init');
        foreach ([['--base'], ['--src='], ['--nope'], ['--base=v9.9.9']] as $args) {
            $process = $this->bin('check-changed-src-tested', $args);
            self::assertSame(2, $process->getExitCode(), implode(' ', $args));
            self::assertStringStartsWith('ERROR: ', $process->getErrorOutput());
        }
    }

    public function testMakeSnippets(): void
    {
        $bin = 'QUALITY_TOOLS_BIN=' . realpath(self::ROOT . '/bin');
        $mk = realpath(self::ROOT . '/resources/make');
        file_put_contents($this->dir . '/Makefile', "RELEASE_PHASES := lint=a b\nRELEASE_PHASE_FLAGS_b := -k\n"
            . "include {$mk}/heavy.mk\ninclude {$mk}/quiet.mk\ninclude {$mk}/release-phases.mk\n"
            . "a:\n\t@echo in-a lock=\$\$DOCKER_HEAVY_LOCK_HELD\nb:\n\t@echo in-b \$(findstring k,\$(firstword \$(MAKEFLAGS))); exit \$(FAILB)\n");
        $make = fn (array $args, array $env = []): Process => $this->exec(['make', '-s', '--no-print-directory', $bin, 'FAILB=0', ...$args], $env);

        // heavy-<target>: through the lock (released after), without it on CI or with HEAVY_LOCK empty.
        $process = $make(['heavy-a', 'HEAVY_LOCK_LABEL=app']);
        self::assertSame([0, "acquired by app: a\nin-a lock=1\n"], $this->outcome($process));
        self::assertDirectoryDoesNotExist($this->dir . '/heavy.lock');
        self::assertSame([0, "in-a lock=\n"], $this->outcome($make(['heavy-a'], ['CI' => 'true'])));
        self::assertSame([0, "in-a lock=\n"], $this->outcome($make(['heavy-a', 'HEAVY_LOCK='])));
        self::assertSame([0, "in-a lock=1\n"], $this->outcome($make(['heavy-a'], ['DOCKER_HEAVY_LOCK_HELD' => '1'])));
        self::assertSame([0, "free\n"], $this->firstLine($make(['heavy-status'])));
        self::assertSame([0, "heavy-status: HEAVY_LOCK is empty (no lock)\n"], $this->outcome($make(['heavy-status', 'HEAVY_LOCK='])));

        // release-phases: every phase in order, flags per phase, stop at the failing phase with the summary.
        $process = $make(['release-phases']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertMatchesRegularExpression("#^in-a lock=\nin-b k\nrelease-check phases:\n  OK   lint \\d+s\n  OK   b \\d+s\n$#", $process->getOutput());
        $process = $make(['release-phases', 'FAILB=3', 'RELEASE_PHASES_TITLE=gate', 'RELEASE_PHASE_DIR=out']);
        self::assertNotSame(0, $process->getExitCode());
        self::assertStringContainsString("gate phases:\n  OK   lint", $process->getOutput());
        self::assertStringContainsString('FAIL b', (string) file_get_contents($this->dir . '/out/summary.txt'));
        $process = $make(['release-phases', 'RELEASE_PHASES=']);
        self::assertSame(2, $process->getExitCode());
        self::assertStringContainsString('release-phases: set RELEASE_PHASES', $process->getOutput());

        // quiet: summary on screen, log in QUIET_LOG_DIR; the JUnit command is passed through.
        $process = $make(['quiet', 'T=a']);
        self::assertMatchesRegularExpression('#^OK a \(\d+s\) — full log: var/quiet/a\.log\n$#', $process->getOutput());
        $process = $make(['quiet', 'T=b', 'FAILB=2', 'QUIET_LOG_DIR=logs', 'QUIET_JUNIT_CMD=echo J', 'QUIET_JUNIT_DIRS=reports']);
        self::assertNotSame(0, $process->getExitCode());
        self::assertStringContainsString('FAIL b (', $process->getOutput());
        self::assertFileExists($this->dir . '/logs/b.log');
        self::assertSame(2, $make(['quiet'])->getExitCode());

        // check-changed-src-tested outside a repository: nothing to compare.
        self::assertSame([0, ''], $this->outcome($make(['check-changed-src-tested', 'COVERAGE_HINT_STRICT=1'])));
    }

    public function testMakeTestChanged(): void
    {
        $mk = realpath(self::ROOT . '/resources/make');
        file_put_contents($this->dir . '/Makefile', "include {$mk}/quiet.mk\n");
        $this->git('init', '-q', '-b', 'main');
        $this->dump(['src/Invoice.php' => "<?php\n", 'tests/InvoiceTest.php' => "<?php\n// Invoice\n"]);
        $this->git('add', '.');
        $this->git('commit', '-qm', 'init');
        $this->git('tag', 'v1.0.0');
        $make = fn (array $args): Process => $this->exec(['make', '-s', '--no-print-directory', 'QUALITY_TOOLS_BIN=' . realpath(self::ROOT . '/bin'), ...$args]);

        self::assertSame([0, "test-changed: no test covers the changes since the last tag\n"], $this->outcome($make(['test-changed'])));

        file_put_contents($this->dir . '/src/Invoice.php', "<?php\n// changed\n");
        // A fake PHPUnit (QUALITY_PHP_EXEC prefix + TEST_CHANGED_PHPUNIT) that writes no JUnit log and fails.
        $process = $make(['test-changed', 'QUALITY_PHP_EXEC=env', "TEST_CHANGED_PHPUNIT=sh -c 'echo phpunit \"\$\$@\"; exit 1' sh"]);
        // make exits 2 when the recipe fails (the fake PHPUnit exit code 1 is the recipe's).
        self::assertSame(2, $process->getExitCode(), $process->getOutput() . $process->getErrorOutput());
        self::assertStringStartsWith("test-changed: 1 test files\nphpunit --log-junit var/quiet/test-changed.junit.xml tests/InvoiceTest.php\n", $process->getOutput());
    }

    /**
     * @return array{int, string}
     */
    private function outcome(Process $process): array
    {
        return [(int) $process->getExitCode(), $process->getOutput()];
    }

    /**
     * @return array{int, string}
     */
    private function firstLine(Process $process): array
    {
        return [(int) $process->getExitCode(), strtok($process->getOutput(), "\n") . "\n"];
    }

    /**
     * @param list<string>               $args
     * @param array<string, string|bool> $env
     */
    private function bin(string $script, array $args, array $env = []): Process
    {
        return $this->exec(['sh', self::ROOT . '/bin/' . $script, ...$args], $env);
    }

    /**
     * @param array<int|string, string>  $command
     * @param array<string, string|bool> $env
     */
    private function exec(array $command, array $env = []): Process
    {
        $process = new Process(array_values($command), $this->dir, [...$this->env, ...$env], null, 60.0);
        $process->run();

        return $process;
    }

    /**
     * @param list<string>               $command
     * @param array<string, string|bool> $env
     */
    private function start(array $command, array $env = []): Process
    {
        $process = new Process($command, $this->dir, [...$this->env, ...$env], null, 60.0);
        $process->start();

        return $process;
    }

    private function waitFor(callable $condition): void
    {
        for ($i = 0; $i < 100 && !$condition(); ++$i) {
            usleep(50_000);
            clearstatcache();
        }
        self::assertTrue($condition(), 'condition not reached');
    }

    private function deadPid(): int
    {
        $process = new Process(['sh', '-c', 'echo $$']);
        $process->run();

        return (int) trim($process->getOutput());
    }

    /**
     * @param array<string, string> $files
     */
    private function dump(array $files): void
    {
        foreach ($files as $path => $content) {
            (new Filesystem())->dumpFile($this->dir . '/' . $path, $content);
        }
    }

    private function git(string ...$args): void
    {
        $process = $this->exec(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', '-c', 'commit.gpgsign=false', ...$args]);
        self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }
}
