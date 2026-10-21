<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * bin/quiet-run (with bin/junit-failures) and bin/changed-tests in temporary directories and git repositories.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class QuietRunScriptsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private const QUIET_ENV = [
        'QUIET_LABEL' => false, 'QUIET_CONTEXT' => false, 'QUIET_SUMMARY' => false, 'QUIET_SUMMARY_LINES' => false,
        'QUIET_ERROR' => false, 'QUIET_JUNIT_DIRS' => false, 'QUIET_JUNIT_CMD' => false,
        'BASE' => false, 'CHANGED_TESTS_SRC' => false, 'CHANGED_TESTS_DIRS' => false, 'CHANGED_TESTS_SUFFIX' => false,
    ];

    private const FAILED_JUNIT = '<?xml version="1.0"?><testsuites><testsuite name="s">'
        . '<testcase name="testOk" class="App\Tests\ATest" file="%s/tests/ATest.php" line="5"/>'
        . '<testcase name="testBad" class="App\Tests\ATest" file="%s/tests/ATest.php" line="9">'
        . '<failure>App\Tests\ATest::testBad' . "\n" . 'Failed asserting that 1 is 2.</failure></testcase>'
        . '</testsuite></testsuites>';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pqt-quiet-' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testSuccessPrintsTheSummaryAndKeepsACleanLog(): void
    {
        $script = 'printf "\033[1;32mrunning\033[0m\n"; printf "  0%%\r 50%%\r100%%\n"; printf "\033]0;title\007OSC gone\n";'
            . ' printf "crlf line\r\n"; echo "OK (12 tests, 30 assertions)"; echo "  Lines:   97.50% (39/40)"; echo noise';

        [$code, $out] = $this->quiet(['var/quiet/unit.log', '--', 'sh', '-c', $script]);

        self::assertSame(0, $code, $out);
        self::assertMatchesRegularExpression('#^OK unit \(\d+s\) — full log: var/quiet/unit\.log\n  OK \(12 tests, 30 assertions\)\n$#', $out);
        self::assertSame("running\n100%\nOSC gone\ncrlf line\nOK (12 tests, 30 assertions)\n  Lines:   97.50% (39/40)\nnoise\n", file_get_contents($this->dir . '/var/quiet/unit.log'));

        // Label, summary pattern and line count from options and environment.
        [$code, $out] = $this->quiet(['--label', 'coverage', '--summary=Lines:|noise', 'var/quiet/unit.log', '--', 'sh', '-c', $script], ['QUIET_SUMMARY_LINES' => '1']);
        self::assertSame(0, $code, $out);
        self::assertMatchesRegularExpression('#^OK coverage \(\d+s\) — full log: var/quiet/unit\.log\n  noise\n$#', $out);
        [, $out] = $this->quiet(['out.log', 'true'], ['QUIET_SUMMARY_LINES' => '0']);
        self::assertMatchesRegularExpression('#^OK out \(\d+s\) — full log: out\.log\n$#', $out);
    }

    public function testFailureKeepsTheExitCodeAndReportsTargetsJunitAndContext(): void
    {
        file_put_contents($this->dir . '/old-junit.xml', \sprintf(self::FAILED_JUNIT, $this->dir, $this->dir));
        touch($this->dir . '/old-junit.xml', time() - 3600);
        $junit = \sprintf(self::FAILED_JUNIT, $this->dir, $this->dir);
        file_put_contents($this->dir . '/junit.tpl', $junit);
        $lines = '';
        for ($i = 1; $i <= 30; ++$i) {
            $lines .= "echo context-{$i}; ";
        }
        $script = 'mkdir -p var/coverage; cp junit.tpl var/coverage/junit.xml; cp junit.tpl var/coverage/other.xml; ' . $lines
            . 'echo "...F.  5 / 5 (100%)"; echo; echo "FAILURES!"; echo "make[1]: *** [Makefile:10: test] Error 1";'
            . ' echo "make: *** [Makefile:20: release-check] Error 2"; echo "make: *** [Makefile:20: release-check] Error 2"; exit 7';

        [$code, $out] = $this->quiet(['var/quiet/release-check.log', '--', 'sh', '-c', $script]);

        self::assertSame(7, $code, $out);
        self::assertMatchesRegularExpression('#^FAIL release-check \(\d+s, exit 7\) — full log: var/quiet/release-check\.log\n#', $out);
        self::assertStringContainsString("Failed targets:\n  [Makefile:10: test] Error 1\n  [Makefile:20: release-check] Error 2\nFailed tests in ", $out);
        self::assertStringContainsString("var/coverage/junit.xml (1):\n  App\\Tests\\ATest::testBad (tests/ATest.php:9)\n      App\\Tests\\ATest::testBad\n      Failed asserting that 1 is 2.\n", $out);
        self::assertStringNotContainsString('other.xml', $out);
        self::assertStringNotContainsString('old-junit.xml', $out);
        $expected = "Last lines before the first error:\n";
        for ($i = 17; $i <= 30; ++$i) {
            $expected .= "  context-{$i}\n";
        }
        self::assertStringEndsWith($expected . "  FAILURES!\n", $out);
        self::assertStringNotContainsString('5 / 5', $out);

        // QUIET_CONTEXT, a custom JUnit command and an extra JUnit directory.
        $script = 'mkdir -p reports; cp junit.tpl reports/phpunit-junit.xml; echo a; echo b; echo "[ERROR] broken"; exit 1';
        [$code, $out] = $this->quiet(['--junit-dir=reports', '--junit-cmd', 'echo JUNIT', 'q.log', '--', 'sh', '-c', $script], ['QUIET_CONTEXT' => '2']);
        self::assertSame(1, $code, $out);
        self::assertStringNotContainsString('Failed targets:', $out);
        self::assertStringContainsString("JUNIT reports/phpunit-junit.xml\nLast lines before the first error:\n  b\n  [ERROR] broken\n", $out);

        // No error line: the end of the log; a command that does not exist keeps its shell exit code.
        [$code, $out] = $this->quiet(['--context=1', 'q.log', '--', 'sh', '-c', 'echo first; echo last; exit 4']);
        self::assertSame(4, $code);
        self::assertStringEndsWith("Last lines before the first error:\n  last\n", $out);
        [$code] = $this->quiet(['q.log', '--', 'pqt-no-such-command-xyz']);
        self::assertSame(127, $code);
        self::assertStringContainsString('pqt-no-such-command-xyz', (string) file_get_contents($this->dir . '/q.log'));
    }

    public function testQuietRunUsage(): void
    {
        [$code, $out] = $this->quiet(['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: quiet-run [options] <log> -- <command> [<args>...]', $out);

        foreach ([[], ['only.log'], ['only.log', '--'], ['--context=x', 'a.log', 'true'], ['--summary-lines', '-1', 'a.log', 'true'], ['--label'], ['--bogus', 'a.log', 'true']] as $args) {
            [$code, $out] = $this->quiet($args);
            self::assertSame(2, $code, implode(' ', $args) . ': ' . $out);
            self::assertStringStartsWith('ERROR: ', $out);
        }
    }

    public function testChangedTestsResolvesChangedTestsAndClasses(): void
    {
        $files = [
            'src/Billing/Invoice.php' => "<?php\nfinal class Invoice {}\n",
            'src/Billing/Customer.php' => "<?php\nfinal class Customer {}\n",
            'src/Unused.php' => "<?php\nfinal class Unused {}\n",
            'tests/Billing/InvoiceTest.php' => "<?php\n// covers Invoice\n",
            'tests/Billing/InvoiceFactoryTest.php' => "<?php\n// covers InvoiceFactory only (not the word on its own)\n",
            'tests/Billing/CustomerTest.php' => "<?php\n// Customer\n",
            'tests/Support/Helper.php' => "<?php\n// Invoice helper, not a test\n",
            'tests/OtherTest.php' => "<?php\n",
            'README.md' => "# demo\n",
        ];
        foreach ($files as $path => $content) {
            (new Filesystem())->dumpFile($this->dir . '/' . $path, $content);
        }
        $this->git('init', '-q', '-b', 'main');
        $this->git('add', '.');
        $this->commit('init');
        $this->git('tag', 'v1.0.0');

        [$code, $out] = $this->changed([]);
        self::assertSame([0, ''], [$code, $out]);

        // Committed source change, working-tree test change, untracked source and test, a deleted test, a doc change.
        file_put_contents($this->dir . '/src/Billing/Invoice.php', "<?php\nfinal class Invoice { public int \$total = 0; }\n");
        $this->git('commit', '-qam', 'feat: total');
        file_put_contents($this->dir . '/tests/OtherTest.php', "<?php\n// changed\n");
        (new Filesystem())->dumpFile($this->dir . '/src/Billing/Refund.php', "<?php\nfinal class Refund {}\n");
        (new Filesystem())->dumpFile($this->dir . '/tests/Billing/RefundTest.php', "<?php\n// Refund\n");
        (new Filesystem())->dumpFile($this->dir . '/tests/Billing/CreditNoteTest.php', "<?php\n// new test\n");
        (new Filesystem())->remove($this->dir . '/tests/Billing/CustomerTest.php');
        file_put_contents($this->dir . '/README.md', "# changed\n");

        [$code, $out] = $this->changed([]);
        self::assertSame(0, $code, $out);
        self::assertSame("tests/Billing/CreditNoteTest.php\ntests/Billing/InvoiceTest.php\ntests/Billing/RefundTest.php\ntests/OtherTest.php\n", $out);

        // From a subdirectory, with BASE pointing at HEAD (only working-tree and untracked changes).
        [$code, $out] = $this->changed([], ['BASE' => 'HEAD'], 'tests/Billing');
        self::assertSame(0, $code, $out);
        self::assertSame("tests/Billing/CreditNoteTest.php\ntests/Billing/RefundTest.php\ntests/OtherTest.php\n", $out);
    }

    public function testChangedTestsWithCustomLayout(): void
    {
        $files = [
            'lib/Mailer.php' => "<?php\nfinal class Mailer {}\n",
            'spec/MailerSpec.php' => "<?php\n// Mailer\n",
            'tests/MailerTest.php' => "<?php\n// Mailer\n",
        ];
        foreach ($files as $path => $content) {
            (new Filesystem())->dumpFile($this->dir . '/' . $path, $content);
        }
        $this->git('init', '-q', '-b', 'main');
        $this->git('add', '.');
        $base = $this->commit('init');
        file_put_contents($this->dir . '/lib/Mailer.php', "<?php\nfinal class Mailer { }\n");

        [$code, $out] = $this->changed(['--base', $base, '--src=lib', '--tests', 'spec/', '--suffix=Spec.php']);
        self::assertSame(0, $code, $out);
        self::assertSame("spec/MailerSpec.php\n", $out);

        [$code, $out] = $this->changed([], ['BASE' => $base, 'CHANGED_TESTS_SRC' => 'lib', 'CHANGED_TESTS_DIRS' => 'spec tests', 'CHANGED_TESTS_SUFFIX' => '.php']);
        self::assertSame(0, $code, $out);
        self::assertSame("spec/MailerSpec.php\ntests/MailerTest.php\n", $out);
    }

    public function testChangedTestsErrors(): void
    {
        [$code, $out] = $this->changed(['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: changed-tests [options]', $out);

        [$code, $out] = $this->changed([]);
        self::assertSame(2, $code);
        self::assertStringContainsString('ERROR: changed-tests must run inside a git repository.', $out);

        $this->git('init', '-q', '-b', 'main');
        $this->commit('init');
        [$code, $out] = $this->changed([]);
        self::assertSame(2, $code);
        self::assertStringContainsString('ERROR: no BASE given and no tag reachable from HEAD', $out);
        [$code, $out] = $this->changed(['--base=v9.9.9']);
        self::assertSame(2, $code);
        self::assertStringContainsString('ERROR: unknown base ref: v9.9.9', $out);
        foreach ([['--base'], ['--suffix='], ['--nope']] as $args) {
            [$code, $out] = $this->changed($args);
            self::assertSame(2, $code, implode(' ', $args) . ': ' . $out);
            self::assertStringStartsWith('ERROR: ', $out);
        }
    }

    /**
     * @param list<string>               $args
     * @param array<string, string|bool> $env
     *
     * @return array{int, string}
     */
    private function quiet(array $args, array $env = []): array
    {
        $process = $this->exec(['sh', self::ROOT . '/bin/quiet-run', ...$args], $env);

        return [(int) $process->getExitCode(), $process->getOutput() . $process->getErrorOutput()];
    }

    /**
     * @param list<string>               $args
     * @param array<string, string|bool> $env
     *
     * @return array{int, string}
     */
    private function changed(array $args, array $env = [], ?string $subdir = null): array
    {
        $process = $this->exec(['sh', self::ROOT . '/bin/changed-tests', ...$args], $env, $subdir);

        return [(int) $process->getExitCode(), $process->getOutput() . $process->getErrorOutput()];
    }

    private function commit(string $message): string
    {
        $process = $this->exec(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', '-c', 'commit.gpgsign=false', 'commit', '-q', '--allow-empty', '-m', $message]);
        self::assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return trim($this->git('rev-parse', 'HEAD'));
    }

    private function git(string ...$args): string
    {
        if ('commit' === $args[0]) {
            $args = ['-c', 'user.name=Test', '-c', 'user.email=test@example.com', '-c', 'commit.gpgsign=false', ...$args];
        }
        $process = $this->exec(['git', ...$args]);
        self::assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return $process->getOutput();
    }

    /**
     * @param array<int|string, string>  $command
     * @param array<string, string|bool> $env
     */
    private function exec(array $command, array $env = [], ?string $subdir = null): Process
    {
        $process = new Process(array_values($command), $this->dir . (null !== $subdir ? '/' . $subdir : ''), [...self::QUIET_ENV, 'GIT_CEILING_DIRECTORIES' => \dirname($this->dir), ...$env], null, 30.0);
        $process->run();

        return $process;
    }
}
