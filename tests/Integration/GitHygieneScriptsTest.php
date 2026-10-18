<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * bin/check-no-agent-coauthor, bin/strip-agent-coauthor, bin/install-git-hooks and resources/make/git-hygiene.mk
 * against real temporary git repositories.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class GitHygieneScriptsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private string $repo;

    protected function setUp(): void
    {
        $this->repo = sys_get_temp_dir() . '/pqt-git-hygiene-' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->repo);
        $this->git('init', '-q', '-b', 'main');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->repo);
    }

    public function testCleanHistoryPasses(): void
    {
        $this->commit("feat: one\n\nCo-authored-by: Ann Human <ann@example.com>");

        [$code, $out] = $this->check([]);

        self::assertSame(0, $code, $out);
        self::assertStringContainsString('OK: no AI agent co-author trailers (cursor, claude, copilot, codex, gemini) in HEAD', $out);
    }

    public function testDetectsEveryDefaultAgent(): void
    {
        $base = $this->commit('chore: base');
        $this->commit("feat: cursor\n\nMade-with: Cursor");
        $this->commit("feat: claude\n\nCo-Authored-By: Claude Opus <noreply@anthropic.com>");
        $this->commit("feat: copilot\n\nco-authored-by: Copilot <175728472+Copilot@users.noreply.github.com>");
        $this->commit("feat: codex\n\nGenerated-by: Codex <codex@openai.com>");
        $this->commit("feat: gemini\n\nAssisted-by: Gemini");

        [$code, $out] = $this->check([]);

        self::assertSame(1, $code);
        foreach (['feat: cursor', 'Made-with: Cursor', 'feat: claude', 'noreply@anthropic.com', 'feat: copilot', 'feat: codex', 'feat: gemini'] as $needle) {
            self::assertStringContainsString($needle, $out);
        }
        self::assertStringNotContainsString('chore: base', $out);
        self::assertStringContainsString('vendor/bin/install-git-hooks', $out);

        // A range and an agent subset.
        [$code, $out] = $this->check(['--agents=cursor', $base . '..HEAD~4']);
        self::assertSame(1, $code);
        self::assertStringContainsString('(cursor) found in ' . $base . '..HEAD~4', $out);
        [$code, $out] = $this->check(['--agents', 'Copilot, gemini', 'HEAD~4']);
        self::assertSame(0, $code, $out);
        self::assertSame(1, $this->check(['HEAD~4'], ['AGENT_COAUTHOR_AGENTS' => 'cursor'])[0]);
    }

    public function testExtraAgentsAndPatterns(): void
    {
        $this->commit("feat: aider\n\nCo-authored-by: aider (gpt-4) <noreply@aider.chat>");
        $this->commit("feat: devin\n\nCo-authored-by: Devin AI <158243242+devin-ai-integration[bot]@users.noreply.github.com>");
        $this->commit("feat: bot\n\nCo-authored-by: Robo <robo@bots.example>");

        self::assertSame(0, $this->check([])[0]);
        self::assertSame(1, $this->check(['--agents=aider'])[0]);
        self::assertSame(1, $this->check(['--agents=devin'])[0]);
        [$code, $out] = $this->check(['--agents=cursor', '--pattern', 'bots\.example']);
        self::assertSame(1, $code);
        self::assertStringContainsString('(cursor, /bots\.example/)', $out);
        self::assertSame(1, $this->check(['--agents='], ['AGENT_COAUTHOR_PATTERN' => 'robo@'])[0]);
        self::assertSame(1, $this->check(['--pattern=robo@', '--agents=cursor'])[0]);
    }

    public function testBaselineAcceptsOlderHistory(): void
    {
        $this->commit("feat: old\n\nCo-Authored-By: Claude <noreply@anthropic.com>");
        $baseline = $this->commit('chore: baseline');
        file_put_contents($this->repo . '/baseline', $baseline . "\n");

        [$code, $out] = $this->check(['--since-file', 'baseline']);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('in ' . $baseline . '..HEAD', $out);
        self::assertSame(0, $this->check(['--since=' . $baseline])[0]);
        self::assertSame(0, $this->check(['--since', $baseline, 'HEAD'])[0]);
        self::assertSame(0, $this->check(['--since-file=baseline'])[0]);

        $this->commit("feat: new\n\nMade-with: Cursor");
        [$code, $out] = $this->check(['--since-file', 'baseline']);
        self::assertSame(1, $code);
        self::assertStringContainsString('feat: new', $out);
        self::assertStringNotContainsString('feat: old', $out);

        // Not an ancestor (rewritten history) or missing file: everything is audited.
        self::assertStringContainsString('feat: old', $this->check(['--since', str_repeat('a', 40)])[1]);
        self::assertStringContainsString('feat: old', $this->check(['--since-file', 'missing'])[1]);
        // An explicit range ignores the baseline.
        self::assertSame(0, $this->check(['--since', $baseline, 'HEAD~2..HEAD~1'])[0]);
    }

    public function testMessageFile(): void
    {
        file_put_contents($this->repo . '/ok', "fix: x\n\nCo-authored-by: Ann <ann@example.com>\n");
        file_put_contents($this->repo . '/bad', "fix: x\n\n  co-authored-by: claude <x@y>\n");

        [$code, $out] = $this->check(['--message-file', 'ok']);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('OK: no AI agent co-author trailers (cursor, claude, copilot, codex, gemini) in ok', $out);
        [$code, $out] = $this->check(['--message-file=bad']);
        self::assertSame(1, $code);
        self::assertStringContainsString("ERROR: AI agent co-author trailers (cursor, claude, copilot, codex, gemini) in bad:\n    co-authored-by: claude <x@y>", $out);
        self::assertSame(2, $this->check(['--message-file', 'nope'])[0]);
    }

    public function testUsageErrors(): void
    {
        $this->commit('chore: base');

        [$code, $out] = $this->check(['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: check-no-agent-coauthor', $out);
        self::assertSame(0, $this->check(['-h'])[0]);

        foreach ([['--agents'], ['--pattern'], ['--since'], ['--since-file'], ['--message-file'], ['--bogus'], ['HEAD', 'HEAD'], ['--agents=skynet'], ['--agents=', '--pattern='], ['no-such-ref']] as $args) {
            self::assertSame(2, $this->check($args)[0], implode(' ', $args));
        }
        self::assertStringContainsString("unknown agent 'skynet'", $this->check(['--agents=skynet'])[1]);
        self::assertStringContainsString('git ref or range not found: no-such-ref', $this->check(['no-such-ref'])[1]);

        $outside = sys_get_temp_dir() . '/pqt-no-git-' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($outside);

        try {
            $process = new Process(['sh', self::ROOT . '/bin/check-no-agent-coauthor'], $outside, ['GIT_CEILING_DIRECTORIES' => \dirname($outside)], null, 30.0);
            $process->run();
            self::assertSame(2, $process->getExitCode());
            self::assertStringContainsString('not inside a git repository', $process->getErrorOutput());
        } finally {
            (new Filesystem())->remove($outside);
        }
    }

    public function testStripRemovesAgentTrailersOnly(): void
    {
        $file = $this->repo . '/MSG';
        file_put_contents($file, "feat: x\n\nBody line.\n\nMade-with: Cursor\nCo-authored-by: Ann <ann@example.com>\n  Co-Authored-By: Claude <noreply@anthropic.com>\nGenerated-with: GitHub Copilot\n\n\n");

        $this->strip([$file]);

        self::assertSame("feat: x\n\nBody line.\n\nCo-authored-by: Ann <ann@example.com>\n", file_get_contents($file));

        file_put_contents($file, "feat: y\n\nCo-authored-by: Robo <robo@bots.example>\nMade-with: Cursor\n");
        $this->strip([$file], ['AGENT_COAUTHOR_AGENTS' => 'claude, skynet', 'AGENT_COAUTHOR_PATTERN' => 'bots\.example']);
        self::assertSame("feat: y\n\nMade-with: Cursor\n", file_get_contents($file));

        // Nothing selected: unchanged. Missing / no file (prepare-commit-msg without a message): no-op.
        $this->strip([$file], ['AGENT_COAUTHOR_AGENTS' => 'skynet']);
        self::assertSame("feat: y\n\nMade-with: Cursor\n", file_get_contents($file));
        self::assertSame(0, $this->strip([$this->repo . '/missing']));
        self::assertSame(0, $this->strip([]));
    }

    public function testInstallGitHooksAndCommitMsgHook(): void
    {
        [$code, $out] = $this->install([]);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString("Installed .githooks/commit-msg\ngit config core.hooksPath .githooks\n", $out);
        self::assertFileDoesNotExist($this->repo . '/.githooks/prepare-commit-msg');
        self::assertTrue(is_executable($this->repo . '/.githooks/commit-msg'));
        self::assertSame(".githooks\n", $this->git('config', 'core.hooksPath'));

        // The installed hook strips trailers from real commits.
        $this->commit("feat: hooked\n\nCo-Authored-By: Claude <noreply@anthropic.com>\nMade-with: Cursor");
        self::assertSame('feat: hooked', trim($this->git('log', '-1', '--format=%B')));
        self::assertSame(0, $this->check([])[0]);

        // Re-running replaces its own hooks; agents baked in; prepare-commit-msg; custom dir without core.hooksPath.
        [$code, $out] = $this->install(['--agents', 'cursor', '--prepare-commit-msg', '--hooks-dir=tools/hooks', '--no-config']);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString("DEFAULT_AGENTS='cursor'", (string) file_get_contents($this->repo . '/tools/hooks/commit-msg'));
        self::assertFileExists($this->repo . '/tools/hooks/prepare-commit-msg');
        self::assertSame(".githooks\n", $this->git('config', 'core.hooksPath'));
        self::assertSame(0, $this->install(['--hooks-dir', 'tools/hooks', '--agents=claude,cursor'])[0]);

        // A foreign hook is kept unless --force.
        file_put_contents($this->repo . '/.githooks/commit-msg', "#!/bin/sh\nexit 0\n");
        [$code, $out] = $this->install([]);
        self::assertSame(1, $code);
        self::assertStringContainsString('was not installed by install-git-hooks; pass --force', $out);
        self::assertSame(0, $this->install(['--force'])[0]);

        [$code, $out] = $this->install(['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: install-git-hooks', $out);
        foreach ([['--hooks-dir'], ['--agents'], ['--agents=a;b'], ['--nope']] as $args) {
            self::assertSame(2, $this->install($args)[0], implode(' ', $args));
        }
    }

    public function testMakeSnippet(): void
    {
        $this->commit("feat: old\n\nCo-Authored-By: Claude <noreply@anthropic.com>");
        $baseline = $this->commit('chore: baseline');
        file_put_contents($this->repo . '/baseline', $baseline);
        $snippet = self::ROOT . '/resources/make/git-hygiene.mk';
        $bin = 'GIT_HYGIENE_BIN=' . self::ROOT . '/bin';

        $make = fn (array $args): Process => $this->exec(['make', '-s', '-f', $snippet, ...$args, $bin]);

        $process = $make(['check-no-agent-coauthor']);
        self::assertSame(2, $process->getExitCode());
        $process = $make(['check-no-agent-coauthor', 'GIT_HYGIENE_SINCE_FILE=baseline']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $process = $make(['check-no-agent-coauthor', 'GIT_HYGIENE_AGENTS=cursor']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        $process = $make(['install-git-hooks', 'GIT_HYGIENE_HOOKS_DIR=hooks', 'GIT_HYGIENE_AGENTS=claude', 'GIT_HYGIENE_HOOK_FLAGS=--prepare-commit-msg']);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString("DEFAULT_AGENTS='claude'", (string) file_get_contents($this->repo . '/hooks/prepare-commit-msg'));
        self::assertSame("hooks\n", $this->git('config', 'core.hooksPath'));
    }

    /**
     * @param list<string>          $args
     * @param array<string, string> $env
     *
     * @return array{int, string}
     */
    private function check(array $args, array $env = []): array
    {
        $process = $this->exec(['sh', self::ROOT . '/bin/check-no-agent-coauthor', ...$args], $env);

        return [(int) $process->getExitCode(), $process->getOutput() . $process->getErrorOutput()];
    }

    /**
     * @param list<string>          $args
     * @param array<string, string> $env
     */
    private function strip(array $args, array $env = []): int
    {
        return (int) $this->exec(['sh', self::ROOT . '/bin/strip-agent-coauthor', ...$args], $env)->getExitCode();
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string}
     */
    private function install(array $args): array
    {
        $process = $this->exec(['sh', self::ROOT . '/bin/install-git-hooks', ...$args]);

        return [(int) $process->getExitCode(), $process->getOutput() . $process->getErrorOutput()];
    }

    private function commit(string $message): string
    {
        if (!$this->exec(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', '-c', 'commit.gpgsign=false', 'commit', '-q', '--allow-empty', '-m', $message])->isSuccessful()) {
            self::fail('git commit failed');
        }

        return trim($this->git('rev-parse', 'HEAD'));
    }

    private function git(string ...$args): string
    {
        $process = $this->exec(['git', ...$args]);
        self::assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return $process->getOutput();
    }

    /**
     * @param array<int|string, string> $command
     * @param array<string, string>     $env
     */
    private function exec(array $command, array $env = []): Process
    {
        $process = new Process(array_values($command), $this->repo, $env, null, 30.0);
        $process->run();

        return $process;
    }
}
