<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * resources/make/coverage-resume.mk with a stub bin/coverage-resume (the PHP side has its own tests): which targets
 * run, what is merged, saved and stamped, and the forced / fallback paths.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CoverageResumeMakeTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** The stub: logs its arguments; plan / merge answer from plan.rc, plan.out and merge.rc; plan writes the stamp. */
    private const STUB = <<<'SH'
        #!/bin/sh
        echo "cr $*" >> calls.log
        case "$1" in
          plan)
            rc=$(cat plan.rc 2>/dev/null || echo 3)
            case " $* " in *" --force-full "*) rc=3 ;; esac
            if [ "$rc" = 0 ]; then echo "coverage: resumed (2 tests, 1 files)" > var/resume/stamp.txt; cat plan.out; else echo "coverage: full" > var/resume/stamp.txt; fi
            exit "$rc" ;;
          merge) exit "$(cat merge.rc 2>/dev/null || echo 0)" ;;
        esac
        exit 0
        SH;

    private const MAKEFILE = <<<'MAKE'
        COVERAGE_RESUME_DIR := var/resume
        COVERAGE_RESUME_FULL := full
        COVERAGE_RESUME_FILES := files
        COVERAGE_RESUME_BIN := sh ./stub.sh
        COVERAGE_RESUME_GATE := sh -c 'echo gate >> calls.log; exit $(GATE_RC)'
        COVERAGE_RESUME_GIT_FILES :=
        COVERAGE_RESUME_COV := var/run.cov
        COVERAGE_RESUME_JUNIT := var/junit.xml
        GATE_RC := 0
        FULL_RC := 0
        include %s/coverage-resume.mk
        full:
        	@echo "full" >> calls.log; [ -n "$(NODATA)" ] || { touch var/run.cov var/junit.xml; }; exit $(FULL_RC)
        files:
        	@echo "files $(FILES)" >> calls.log
        MAKE;

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pqt-coverage-resume-mk-' . bin2hex(random_bytes(4));
        $fs = new Filesystem();
        $fs->mkdir([$this->dir . '/var/resume']);
        $fs->dumpFile($this->dir . '/stub.sh', self::STUB);
        $fs->dumpFile($this->dir . '/Makefile', \sprintf(self::MAKEFILE, realpath(self::ROOT . '/resources/make')));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testTheFullSuiteWhenThePlanAsksForIt(): void
    {
        $process = $this->make();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame("php-coverage: passed — coverage: full\n", $process->getOutput());
        self::assertSame([
            'cr plan --state=var/resume',
            'full',
            'gate',
            'cr save --state=var/resume --coverage=var/run.cov --junit=var/junit.xml --status=passed --commit=',
        ], $this->calls());
    }

    public function testAResume(): void
    {
        file_put_contents($this->dir . '/plan.rc', '0');
        file_put_contents($this->dir . '/plan.out', "tests/ATest.php\ntests/BTest.php\n");

        $process = $this->make(['COVERAGE_RESUME_OPTIONS=--always=tests/Gate/*']);

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame("php-coverage: passed — coverage: resumed (2 tests, 1 files)\n", $process->getOutput());
        self::assertSame([
            'cr plan --state=var/resume --always=tests/Gate/*',
            'files tests/ATest.php tests/BTest.php',
            'cr merge --state=var/resume --always=tests/Gate/* --coverage=var/coverage/resume-part.cov --junit=var/coverage/resume-part.xml --clover=var/coverage/clover.xml',
            'gate',
            'cr save --state=var/resume --always=tests/Gate/* --coverage=var/resume/merged.cov --junit=var/coverage/resume-part.xml --status=passed --commit=',
        ], $this->calls());
    }

    public function testAFailedGateAfterAResumeIsKeptAsFailed(): void
    {
        file_put_contents($this->dir . '/plan.rc', '0');
        file_put_contents($this->dir . '/plan.out', "tests/ATest.php\n");

        $process = $this->make(['GATE_RC=1']);

        self::assertNotSame(0, $process->getExitCode());
        self::assertSame("php-coverage: failed — coverage: resumed (2 tests, 1 files)\n", $process->getOutput());
        self::assertStringContainsString('--status=failed', $this->calls()[4]);
    }

    public function testAMergeThatCannotMergeFallsBackToTheFullSuite(): void
    {
        file_put_contents($this->dir . '/plan.rc', '0');
        file_put_contents($this->dir . '/plan.out', "tests/ATest.php\n");
        file_put_contents($this->dir . '/merge.rc', '3');

        $process = $this->make();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame("php-coverage: passed — coverage: full\n", $process->getOutput());
        self::assertSame(['cr plan --state=var/resume --force-full', 'full', 'gate'], \array_slice($this->calls(), 3, 3));
    }

    public function testAMergeWithoutDataFails(): void
    {
        file_put_contents($this->dir . '/plan.rc', '0');
        file_put_contents($this->dir . '/plan.out', "tests/ATest.php\n");
        file_put_contents($this->dir . '/merge.rc', '1');

        $process = $this->make();

        self::assertNotSame(0, $process->getExitCode());
        self::assertSame("php-coverage: the resumed run left no data to merge\n", $process->getOutput());
    }

    public function testForcedRunsAndFailures(): void
    {
        file_put_contents($this->dir . '/plan.rc', '0');
        foreach (['FORCE_COVERAGE=1', 'NO_RESUME=1', 'COVERAGE_RESUME=0'] as $force) {
            @unlink($this->dir . '/calls.log');
            self::assertSame("php-coverage: passed — coverage: full\n", $this->make([$force])->getOutput(), $force);
            self::assertSame('cr plan --state=var/resume --force-full', $this->calls()[0]);
        }

        @unlink($this->dir . '/calls.log');
        $process = $this->make(['FULL_RC=1', 'COVERAGE_RESUME=no']);
        self::assertNotSame(0, $process->getExitCode());
        self::assertSame(['full', 'cr save --state=var/resume --coverage=var/run.cov --junit=var/junit.xml --status=failed --commit='], \array_slice($this->calls(), 1), 'no gate after failing tests');

        @unlink($this->dir . '/calls.log');
        $process = $this->make(['NODATA=1', 'FORCE_COVERAGE=1']);
        self::assertNotSame(0, $process->getExitCode());
        self::assertStringContainsString('php-coverage: the full run left no var/run.cov / var/junit.xml — nothing kept', $process->getOutput());
        self::assertSame('cr forget --state=var/resume', $this->calls()[2]);

        file_put_contents($this->dir . '/plan.rc', '2');
        self::assertSame(2, $this->make()->getExitCode(), 'a plan error stops the phase');
        self::assertSame(2, $this->make(['COVERAGE_RESUME_FULL='])->getExitCode());
        self::assertStringContainsString('set COVERAGE_RESUME_FULL and COVERAGE_RESUME_FILES', $this->make(['COVERAGE_RESUME_FILES='])->getOutput());
    }

    public function testGitFileListAncestryGuardAndForget(): void
    {
        $git = fn (string ...$args): Process => $this->exec(['git', '-c', 'user.name=T', '-c', 'user.email=t@example.com', '-c', 'commit.gpgsign=false', ...array_values($args)]);
        $git('init', '-q', '-b', 'main');
        file_put_contents($this->dir . '/.gitignore', "var/\ncalls.log\n");
        $git('add', '.');
        $git('commit', '-qm', 'one');
        $first = trim($git('rev-parse', 'HEAD')->getOutput());
        $git('commit', '-q', '--allow-empty', '-m', 'two');
        $git('commit', '-q', '--allow-empty', '-m', 'three');
        file_put_contents($this->dir . '/plan.rc', '0');
        file_put_contents($this->dir . '/plan.out', "tests/ATest.php\n");
        file_put_contents($this->dir . '/var/resume/state.json', '{"format":1,"commit":"' . $first . '","fullCommit":"' . $first . '"}');

        $process = $this->make(['COVERAGE_RESUME_GIT_FILES=1', 'COVERAGE_RESUME_MAX_COMMITS=1']);

        self::assertStringStartsWith("php-coverage: the kept run is not an ancestor of HEAD within 1 commit(s) — full suite\n", $process->getOutput());
        self::assertSame('cr plan --state=var/resume --files-from=var/resume/files.txt --force-full', $this->calls()[0]);
        $listed = explode("\n", trim((string) file_get_contents($this->dir . '/var/resume/files.txt')));
        sort($listed);
        self::assertSame(['.gitignore', 'Makefile', 'plan.out', 'plan.rc', 'stub.sh'], $listed);
        self::assertStringEndsWith('--commit=' . trim($git('rev-parse', 'HEAD')->getOutput()), $this->calls()[3]);

        @unlink($this->dir . '/calls.log');
        self::assertSame("php-coverage: passed — coverage: resumed (2 tests, 1 files)\n", $this->make(['COVERAGE_RESUME_MAX_COMMITS=2'])->getOutput());

        @unlink($this->dir . '/calls.log');
        $this->make([], 'coverage-resume-forget');
        self::assertSame(['cr forget --state=var/resume'], $this->calls());
    }

    /**
     * @param list<string> $args
     */
    private function make(array $args = [], string $target = 'php-coverage-resume'): Process
    {
        return $this->exec(['make', '-s', '--no-print-directory', $target, ...$args]);
    }

    /**
     * @param list<string> $command
     */
    private function exec(array $command): Process
    {
        $process = new Process($command, $this->dir, ['MAKEFLAGS' => false, 'MFLAGS' => false, 'MAKELEVEL' => false, 'FORCE_COVERAGE' => false, 'NO_RESUME' => false, 'COVERAGE_RESUME' => false, 'GIT_CEILING_DIRECTORIES' => \dirname($this->dir)], null, 60.0);
        $process->run();

        return $process;
    }

    /**
     * @return list<string>
     */
    private function calls(): array
    {
        return explode("\n", trim((string) file_get_contents($this->dir . '/calls.log')));
    }
}
