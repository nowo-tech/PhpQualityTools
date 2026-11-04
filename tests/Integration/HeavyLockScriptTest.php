<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * bin/heavy-lock against the workspace copy (docker-heavy-lock.sh), concurrency of the group promise, and hostile or
 * malformed lock files, in temporary directories (never the machine's lock).
 *
 * The workspace copy is HEAVY_LOCK_REFERENCE when that variable names an executable file, else the vendored
 * tests/Fixtures/heavy-lock/docker-heavy-lock.sh (bin/heavy-lock with VARIANT_IGNORE_OWN_REPO=1).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class HeavyLockScriptTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const FIXTURE = __DIR__ . '/../Fixtures/heavy-lock/docker-heavy-lock.sh';

    private string $dir;

    /** @var array<string, string|bool> */
    private array $env;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pqt-heavylock-' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->dir);
        $this->env = [
            'DOCKER_HEAVY_LOCK_DIR' => $this->dir . '/heavy.lock',
            'DOCKER_HEAVY_QUEUE_DIR' => $this->dir . '/heavy.queue',
            'DOCKER_HEAVY_STALE_LOG' => $this->dir . '/heavy.stale.log',
            'DOCKER_HEAVY_GROUP_DIR' => false,
            'DOCKER_HEAVY_SLOTS' => '4', 'DOCKER_HEAVY_MIN_MEM_MB' => '0', 'DOCKER_HEAVY_MAXLOAD' => '100000', 'MAXLOAD' => '100000',
            'DOCKER_HEAVY_POLL' => '1', 'POLL' => '1', 'DOCKER_HEAVY_LOCK_MAXAGE' => false, 'DOCKER_HEAVY_GROUP' => false,
            'DOCKER_HEAVY_IGNORE_REPOS' => false, 'DOCKER_HEAVY_PRIORITY' => false, 'DOCKER_HEAVY_LOCK_PID' => false,
            'DOCKER_HEAVY_LOCK_HELD' => false, 'CI' => false, 'MAKEFLAGS' => false, 'MFLAGS' => false, 'MAKELEVEL' => false,
            'GIT_CEILING_DIRECTORIES' => \dirname($this->dir),
        ];
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testTheVendoredWorkspaceCopyIsTheVariantOfBinHeavyLock(): void
    {
        $bin = (string) file_get_contents(self::ROOT . '/bin/heavy-lock');
        $lines = explode("\n", str_replace("\nVARIANT_IGNORE_OWN_REPO=0\n", "\nVARIANT_IGNORE_OWN_REPO=1\n", $bin));
        array_splice($lines, 1, 0, ['# docker-heavy-lock.sh: generated from nowo-tech/php-quality-tools bin/heavy-lock (workspace variant); edit it there.']);
        self::assertSame(implode("\n", $lines), file_get_contents(self::FIXTURE), 'regenerate tests/Fixtures/heavy-lock/docker-heavy-lock.sh');
        self::assertTrue(is_executable(self::FIXTURE));
    }

    public function testBothCopiesShareSlotsGroupsAndQueue(): void
    {
        // A workspace-copy job holds group beacon: a heavy-lock waiter of that group waits, another group runs beside it.
        $holder = $this->start([$this->reference(), 'run', 'beacon: release-check', 'sleep', '4'], ['DOCKER_HEAVY_GROUP' => 'beacon']);
        $this->waitFor(fn (): bool => is_file($this->dir . '/heavy.lock/owner') && 6 === \count(file($this->dir . '/heavy.lock/owner') ?: []));
        $waiter = $this->start(['sh', self::ROOT . '/bin/heavy-lock', 'acquire', 'pqt: beacon'], ['DOCKER_HEAVY_GROUP' => 'beacon']);
        $this->waitFor(fn (): bool => [] !== glob($this->dir . '/heavy.queue/*'));
        $other = $this->pqt(['acquire', 'pqt: other'], ['DOCKER_HEAVY_GROUP' => 'other']);
        self::assertSame("acquired by pqt: other (slot 2/4, group other)\n", $other->getOutput(), $other->getErrorOutput());

        // Each copy reads the other's slots, group tokens and tickets.
        $status = $this->ref(['status'])->getOutput();
        self::assertMatchesRegularExpression('#^held \(slot 1/4, group beacon\): beacon: release-check since \S+ pid \d+\nheld \(slot 2/4, group other\): pqt: other since \S+ pid \d+\nqueue \(oldest first\):\n  pqt: beacon \[group beacon\] \[\d+-\d+\]\n#', $status);
        self::assertSame($this->withoutLoad($status), $this->withoutLoad($this->pqt(['status'])->getOutput()));
        self::assertFileExists($this->dir . '/heavy.group/slot.2.lock');

        usleep(1_000_000);
        self::assertTrue($waiter->isRunning());
        $holder->wait();
        self::assertSame(0, $holder->getExitCode(), $holder->getErrorOutput());
        $waiter->wait();
        self::assertSame("acquired by pqt: beacon (slot 1/4, group beacon)\n", $waiter->getOutput());

        // The other way round: a heavy-lock holder makes a workspace-copy waiter of its group wait.
        $refWaiter = $this->start([$this->reference(), 'acquire', 'ref: beacon'], ['DOCKER_HEAVY_GROUP' => 'beacon']);
        usleep(1_500_000);
        self::assertTrue($refWaiter->isRunning());
        $this->exec(['sh', '-c', 'sh "$0" release; true', self::ROOT . '/bin/heavy-lock']);
        $this->exec(['sh', '-c', 'sh "$0" release; true', self::ROOT . '/bin/heavy-lock']);
        $refWaiter->wait();
        self::assertSame("acquired by ref: beacon (slot 1/4, group beacon)\n", $refWaiter->getOutput());
        self::assertSame("released heavy.lock\n", $this->exec(['sh', '-c', '"$0" release; true', $this->reference()])->getOutput());
        self::assertSame([], glob($this->dir . '/heavy.lock*'));
    }

    public function testOneGroupNeverOverlapsUnderConcurrentWaitersOfBothCopies(): void
    {
        // 6 workers x 25 runs of one group, alternating the two copies, 20 ms poll: the job body detects any overlap.
        $script = <<<'SH'
            inside="$WORK/inside"
            job='if mkdir "$0" 2>/dev/null; then sleep 0.05; rmdir "$0"; else echo OVERLAP; fi'
            for w in 1 2 3 4 5 6; do
              (
                i=0
                while [ "$i" -lt 25 ]; do
                  if [ $((w % 2)) -eq 0 ]; then "$REF" run "X: ref$w" sh -c "$job" "$inside"; else sh "$PQT" run "X: pqt$w" sh -c "$job" "$inside"; fi
                  i=$((i + 1))
                done
              ) > "$WORK/w$w.out" 2>&1 &
            done
            wait
            cat "$WORK"/w*.out
            SH;
        $process = new Process(['sh', '-c', $script], $this->dir, [...$this->env, 'DOCKER_HEAVY_POLL' => '0.02', 'POLL' => '0.02', 'DOCKER_HEAVY_GROUP' => 'X',
            'WORK' => $this->dir, 'REF' => $this->reference(), 'PQT' => self::ROOT . '/bin/heavy-lock'], null, 900.0);
        $process->run();
        $out = $process->getOutput();

        self::assertSame(150, substr_count($out, 'acquired by X: '), $out . $process->getErrorOutput());
        self::assertSame(0, substr_count($out, 'OVERLAP'), $out);
        self::assertStringNotContainsString('stale', $out);
        self::assertSame([], glob($this->dir . '/heavy.lock*'));
    }

    public function testControlCharactersInLabelsCannotShiftTheOwnerFile(): void
    {
        foreach ([['sh', self::ROOT . '/bin/heavy-lock'], [$this->reference()]] as $copy) {
            $holder = $this->start([...$copy, 'run', "app: build\nfrom a commit\n-\nL[\$(touch PWNED)]\r\e[2J", 'sleep', '3']);
            $this->waitFor(fn (): bool => is_file($this->dir . '/heavy.lock/owner') && 6 === \count(file($this->dir . '/heavy.lock/owner') ?: []));
            $owner = explode("\n", (string) file_get_contents($this->dir . '/heavy.lock/owner'));
            self::assertSame(['app: build from a commit - L[$(touch PWNED)]  [2J', (string) $holder->getPid(), 'app'], [$owner[0], $owner[2], $owner[4]]);

            // A second job of the same group waits for the first instead of judging it stale.
            $second = $this->start(['sh', self::ROOT . '/bin/heavy-lock', 'acquire', 'app: other']);
            usleep(1_500_000);
            self::assertTrue($second->isRunning());
            self::assertTrue($holder->isRunning());
            $holder->wait();
            $second->wait();
            self::assertSame("acquired by app: other (slot 1/4, group app)\n", $second->getOutput());
            $this->exec(['sh', '-c', 'sh "$0" release; true', self::ROOT . '/bin/heavy-lock']);
            self::assertFileDoesNotExist($this->dir . '/heavy.stale.log');
        }
        self::assertFileDoesNotExist($this->dir . '/PWNED');
    }

    public function testMalformedOrForgedOwnerFilesAreJudgedByAgeOnly(): void
    {
        $shells = [['sh']];
        if ($this->has('bash')) {
            $shells[] = ['bash', '--posix'];
        }
        // A PID line that is not a PID (a date, 0, 1) is never "gone"; an epoch line that is not a number is never evaluated.
        foreach (["x\nt\n2026-11-04T09:09:45Z\nL[\$(touch PWNED)]\ng\n", "x\nt\n0\nL[\$(touch PWNED)]\ng\n", "x\nt\n1\n12x\ng\n", "x\nt\n-\n1+\$(touch PWNED)\ng\n"] as $content) {
            (new Filesystem())->mkdir($this->dir . '/heavy.lock');
            file_put_contents($this->dir . '/heavy.lock/owner', $content);
            foreach ($shells as $shell) {
                $status = $this->exec([...$shell, self::ROOT . '/bin/heavy-lock', 'status'])->getOutput();
                self::assertStringStartsWith('held (slot 1/4, group g): x since t pid ', $status);
                self::assertStringNotContainsString('stale', $status);
            }
            $other = $this->pqt(['acquire', 'h: other'], ['DOCKER_HEAVY_LOCK_PID' => (string) getmypid()]);
            self::assertSame("acquired by h: other (slot 2/4, group h)\n", $other->getOutput(), $other->getErrorOutput());
            self::assertDirectoryExists($this->dir . '/heavy.lock');
            self::assertSame("released slot 2\n", $this->pqt(['release', (string) getmypid()])->getOutput());
            (new Filesystem())->remove($this->dir . '/heavy.lock');
        }
        self::assertFileDoesNotExist($this->dir . '/PWNED');
        self::assertFileDoesNotExist($this->dir . '/heavy.stale.log');

        // The same malformed holder, once older than the maximum age (by its directory's mtime), is stale.
        (new Filesystem())->mkdir($this->dir . '/heavy.lock');
        file_put_contents($this->dir . '/heavy.lock/owner', "x\nt\nnope\nnope\ng\n");
        touch($this->dir . '/heavy.lock', time() - 7200);
        $process = $this->pqt(['acquire', 'g: next']);
        self::assertSame("acquired by g: next (slot 1/4, group g)\n", $process->getOutput());
        self::assertStringContainsString('stale lock of "x" (slot 1) removed by "g: next" (held 120 min without a live PID)', $process->getErrorOutput());
    }

    public function testEverySlotPresentCountsWhateverTheLocalSlotSetting(): void
    {
        (new Filesystem())->mkdir($this->dir . '/heavy.lock.3');
        file_put_contents($this->dir . '/heavy.lock.3/owner', "G: long\n2026-01-01T00:00:00Z\n" . getmypid() . "\n" . time() . "\nG\n");
        $two = ['DOCKER_HEAVY_SLOTS' => '2'];
        $waiter = $this->start(['sh', self::ROOT . '/bin/heavy-lock', 'acquire', 'G: short'], [...$two, 'DOCKER_HEAVY_GROUP' => 'G']);
        usleep(1_500_000);
        self::assertTrue($waiter->isRunning());
        self::assertStringStartsWith('held (slot 3/2, group G): G: long since ', $this->pqt(['status'], $two)->getOutput());
        // Its holder releases by PID although slot 3 is beyond this copy's setting.
        self::assertSame("released slot 3\n", $this->pqt(['release', (string) getmypid()], $two)->getOutput());
        $waiter->wait();
        self::assertSame("acquired by G: short (slot 1/2, group G)\n", $waiter->getOutput());
    }

    public function testTheGroupKernelLockBlocksTheGroupUntilItsHolderDies(): void
    {
        (new Filesystem())->mkdir($this->dir . '/heavy.group');
        $groupLock = $this->dir . '/heavy.group/G-' . trim($this->exec(['sh', '-c', "printf %s G | cksum | cut -d' ' -f1"])->getOutput()) . '.lock';
        // The locked descriptor lives in the process itself (no child inherits it), as in heavy-lock.
        $holder = $this->start(['sh', '-c', 'exec 9>>"$0" && flock 9 && exec sleep 60', $groupLock]);
        $this->waitFor(fn (): bool => 0 !== $this->exec(['flock', '-n', $groupLock, 'true'])->getExitCode());

        $waiter = $this->start(['sh', self::ROOT . '/bin/heavy-lock', 'acquire', 'G: next']);
        usleep(1_500_000);
        self::assertTrue($waiter->isRunning(), $waiter->getOutput() . $waiter->getErrorOutput() . $this->pqt(['status'])->getOutput());
        self::assertSame("free (0/4 slots held)\n", strtok($this->pqt(['status'])->getOutput(), "\n") . "\n");

        // SIGKILL of whoever holds the group lock frees it: no stale state is left to judge.
        $holder->signal(9);
        $waiter->wait();
        self::assertSame("acquired by G: next (slot 1/4, group G)\n", $waiter->getOutput());
        self::assertSame('', $waiter->getErrorOutput());
    }

    public function testSigkillOfTheJobOrOfTheHolderFreesItsLocksAtOnce(): void
    {
        $job = ['sh', '-c', 'echo $$ > "$0"; exec sleep 60', $this->dir . '/job.pid'];
        $env = ['DOCKER_HEAVY_GROUP' => 'K'];

        // The job is killed: run notices, removes its legacy directory and exits; the next job of the group starts.
        $run = $this->start(['sh', self::ROOT . '/bin/heavy-lock', 'run', 'K: victim', ...$job], $env);
        $this->waitFor(fn (): bool => is_file($this->dir . '/job.pid') && '' !== trim((string) file_get_contents($this->dir . '/job.pid')));
        $this->exec(['kill', '-9', trim((string) file_get_contents($this->dir . '/job.pid'))]);
        $run->wait();
        self::assertSame(137, $run->getExitCode());
        self::assertDirectoryDoesNotExist($this->dir . '/heavy.lock');
        $started = microtime(true);
        self::assertSame("acquired by K: next (slot 1/4, group K)\n", $this->pqt(['acquire', 'K: next'], [...$env, 'DOCKER_HEAVY_LOCK_PID' => (string) getmypid()])->getOutput());
        self::assertLessThan(2.0, microtime(true) - $started);
        $this->pqt(['release', (string) getmypid()]);
        unlink($this->dir . '/job.pid');

        // The run process itself is killed (no trap runs): the kernel drops its locks; the legacy directory it left
        // is cleared by the next holder of that slot, which starts without waiting for any age.
        $run = $this->start(['sh', self::ROOT . '/bin/heavy-lock', 'run', 'K: victim', ...$job], $env);
        $this->waitFor(fn (): bool => is_file($this->dir . '/job.pid') && '' !== trim((string) file_get_contents($this->dir . '/job.pid')));
        // A waiter of the group, killed while it waits, leaves nothing that blocks the queue.
        $waiter = $this->start(['sh', self::ROOT . '/bin/heavy-lock', 'acquire', 'K: killed waiter'], $env);
        $runWaiter = $this->start(['sh', self::ROOT . '/bin/heavy-lock', 'run', 'K: killed run waiter', 'true'], $env);
        $this->waitFor(fn (): bool => 2 === \count(glob($this->dir . '/heavy.queue/*') ?: []));
        $waiter->signal(9);
        $runWaiter->signal(9);
        $run->signal(9);
        $started = microtime(true);
        $process = $this->pqt(['acquire', 'K: after'], [...$env, 'DOCKER_HEAVY_LOCK_PID' => (string) getmypid()]);
        self::assertSame("acquired by K: after (slot 1/4, group K)\n", $process->getOutput());
        self::assertMatchesRegularExpression('#stale lock of "K: victim" \(slot 1\) removed by "K: after" \(pid \d+ gone\)#', $process->getErrorOutput());
        self::assertLessThan(4.0, microtime(true) - $started);
        $this->exec(['kill', '-9', trim((string) file_get_contents($this->dir . '/job.pid'))]);
        $this->pqt(['release', (string) getmypid()]);
        $this->waitFor(fn (): bool => [] === glob($this->dir . '/heavy.queue/*'));
    }

    public function testMisconfiguredDirectoriesAndForeignFilesAreNeverDeleted(): void
    {
        // A lock directory pointed at real data older than the maximum age: refused, never removed.
        $this->dump(['data/keep/file.txt' => 'keep', 'data/owner' => "old\nt\n-\n1\n"]);
        touch($this->dir . '/data', time() - 7200);
        $env = ['DOCKER_HEAVY_LOCK_DIR' => $this->dir . '/data', 'DOCKER_HEAVY_GROUP_DIR' => $this->dir . '/groups'];
        $process = $this->pqt(['acquire', 'app: x'], $env);
        self::assertSame("acquired by app: x (slot 2/4, group app)\n", $process->getOutput());
        self::assertStringContainsString('not removing ' . $this->dir . '/data: it holds more than an owner file', $process->getErrorOutput());
        self::assertFileExists($this->dir . '/data/keep/file.txt');

        // A queue directory with other files: status and waiters leave them alone.
        $this->dump(['heavy.queue/notes.txt' => 'n', 'heavy.queue/report-2026.md' => 'r', 'heavy.queue/1-1' => 'x']);
        $this->pqt(['status']);
        self::assertSame(0, $this->pqt(['release', '999999'])->getExitCode());
        $this->pqt(['acquire', 'other: y']);
        self::assertFileExists($this->dir . '/heavy.queue/notes.txt');
        self::assertFileExists($this->dir . '/heavy.queue/report-2026.md');
        self::assertFileExists($this->dir . '/heavy.queue/1-1');

        // A stale log that is a symlink is never written through.
        $this->dump(['target' => '']);
        symlink($this->dir . '/target', $this->dir . '/heavy.stale.log');
        (new Filesystem())->mkdir($this->dir . '/heavy.lock.2');
        file_put_contents($this->dir . '/heavy.lock.2/owner', "gone: job\nt\n" . $this->deadPid() . "\n" . time() . "\ngone\n");
        $process = $this->pqt(['acquire', 'z: job'], ['DOCKER_HEAVY_SLOTS' => '3']);
        self::assertSame("acquired by z: job (slot 2/3, group z)\n", $process->getOutput(), $process->getErrorOutput());
        self::assertStringContainsString('not writing ' . $this->dir . '/heavy.stale.log: a symlink', $process->getErrorOutput());
        self::assertSame('', file_get_contents($this->dir . '/target'));

        // A queue directory that is a symlink is refused.
        symlink($this->dir . '/heavy.queue', $this->dir . '/qlink');
        $process = $this->pqt(['acquire', 'q: job'], ['DOCKER_HEAVY_QUEUE_DIR' => $this->dir . '/qlink']);
        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString('is a symlink or belongs to another user; refusing to use it', $process->getErrorOutput());
    }

    public function testAnotherUsersSlotIsNeverRemoved(): void
    {
        if (!\function_exists('posix_getuid') || 0 !== posix_getuid()) {
            self::markTestSkipped('needs root to create a directory of another user');
        }
        (new Filesystem())->mkdir($this->dir . '/heavy.lock');
        file_put_contents($this->dir . '/heavy.lock/owner', "foreign\nt\n" . $this->deadPid() . "\n1\nforeign-group\n");
        self::assertTrue(chown($this->dir . '/heavy.lock', 'nobody'));
        self::assertStringStartsWith("held (slot 1/4): a symlink or another user's directory (never removed)\n", $this->pqt(['status'])->getOutput());
        self::assertSame("acquired by f: mine (slot 2/4, group f)\n", $this->pqt(['acquire', 'f: mine'], ['DOCKER_HEAVY_GROUP' => 'f'])->getOutput());
        self::assertDirectoryExists($this->dir . '/heavy.lock');
    }

    public function testHeavyMakeTargetsQuoteTheLabel(): void
    {
        $dir = $this->dir . '/app$(touch PWNEDMK)`touch PWNEDBT`\'q';
        (new Filesystem())->mkdir($dir);
        file_put_contents($dir . '/Makefile', 'include ' . realpath(self::ROOT . '/resources/make') . "/heavy.mk\na:\n\t@echo in-a\n");
        $process = $this->exec(['make', '-s', '--no-print-directory', 'QUALITY_TOOLS_BIN=' . realpath(self::ROOT . '/bin'), 'heavy-a'], [], $dir);
        self::assertSame("acquired by app\$(touch PWNEDMK)`touch PWNEDBT`'q: a (slot 1/4, group app\$(touch)\nin-a\n", $process->getOutput(), $process->getErrorOutput());
        self::assertSame([], glob($this->dir . '/PWNED*') ?: []);
        self::assertSame([], glob($dir . '/PWNED*') ?: []);
    }

    private function withoutLoad(string $status): string
    {
        return (string) preg_replace('#^load: .*\n#m', '', $status);
    }

    private function reference(): string
    {
        $reference = getenv('HEAVY_LOCK_REFERENCE');

        return \is_string($reference) && '' !== $reference && is_executable($reference) ? $reference : (string) realpath(self::FIXTURE);
    }

    /**
     * @param list<string>               $args
     * @param array<string, string|bool> $env
     */
    private function pqt(array $args, array $env = []): Process
    {
        return $this->exec(['sh', self::ROOT . '/bin/heavy-lock', ...$args], $env);
    }

    /**
     * @param list<string>               $args
     * @param array<string, string|bool> $env
     */
    private function ref(array $args, array $env = []): Process
    {
        return $this->exec([$this->reference(), ...$args], $env);
    }

    /**
     * @param list<string>               $command
     * @param array<string, string|bool> $env
     */
    private function exec(array $command, array $env = [], ?string $cwd = null): Process
    {
        $process = new Process($command, $cwd ?? $this->dir, [...$this->env, ...$env], null, 120.0);
        $process->run();

        return $process;
    }

    /**
     * @param list<string>               $command
     * @param array<string, string|bool> $env
     */
    private function start(array $command, array $env = []): Process
    {
        $process = new Process($command, $this->dir, [...$this->env, ...$env], null, 120.0);
        $process->start();

        return $process;
    }

    private function waitFor(callable $condition): void
    {
        for ($i = 0; $i < 200 && !$condition(); ++$i) {
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

    private function has(string $command): bool
    {
        return 0 === (new Process(['sh', '-c', 'command -v "$0"', $command]))->run();
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
}
