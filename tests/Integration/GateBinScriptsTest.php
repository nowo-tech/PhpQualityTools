<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Runs the real bin/ scripts in a child PHP process (autoloader discovery, argv, env, exit codes).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class GateBinScriptsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private ?string $repo = null;

    protected function tearDown(): void
    {
        if (null !== $this->repo) {
            (new Filesystem())->remove($this->repo);
        }
    }

    public function testComposerJsonDeclaresEveryScript(): void
    {
        $composer = json_decode((string) file_get_contents(self::ROOT . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($composer);
        self::assertSame(['bin/assert-latest-tag', 'bin/assert-release-proof', 'bin/check-asset-budgets', 'bin/check-coverage-threshold', 'bin/check-doc-links', 'bin/check-module-boundaries', 'bin/check-no-inline-svg', 'bin/clean-stale-pid-paths'], $composer['bin'] ?? null);
        foreach ($composer['bin'] as $script) {
            self::assertTrue(is_executable(self::ROOT . '/' . $script), $script);
        }
        self::assertTrue(is_executable(self::ROOT . '/bin/assert-latest-tag'));
        self::assertTrue(is_executable(self::ROOT . '/bin/assert-release-proof'));
        self::assertTrue(is_executable(self::ROOT . '/bin/check-coverage-threshold'));
        self::assertTrue(is_executable(self::ROOT . '/bin/check-module-boundaries'));
        self::assertTrue(is_executable(self::ROOT . '/bin/clean-stale-pid-paths'));
    }

    public function testAssertLatestTagScriptReadsRealGitTags(): void
    {
        $repo = $this->createRepository(['v1.2.0', 'v1.10.0', 'v1.9.0', 'v2.0.0-rc1']);

        [$code, $out] = $this->runScript('assert-latest-tag', ['v1.10.0'], [], $repo);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('OK: v1.10.0 is the latest release tag.', $out);

        $outputFile = $repo . '/github-output';
        [$code, $out] = $this->runScript('assert-latest-tag', ['--github-output'], ['GITHUB_REF_NAME' => 'v1.9.0', 'GITHUB_OUTPUT' => $outputFile], $repo);
        self::assertSame(0, $code, $out);
        self::assertSame("deploy=false\nlatest=v1.10.0\n", file_get_contents($outputFile));

        [$code, $out] = $this->runScript('assert-latest-tag', ['v1.2.0'], [], $repo);
        self::assertSame(1, $code);
        self::assertStringContainsString('ERROR: v1.2.0 is not the latest release (v1.10.0).', $out);
    }

    public function testAssertReleaseProofScriptReadsTheAnnotatedTag(): void
    {
        $repo = $this->createRepository(['v1.0.0']);
        $git = ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', '-c', 'tag.gpgsign=false'];
        $head = new Process(['git', 'rev-parse', 'HEAD'], $repo);
        $head->mustRun();
        $sha = trim($head->getOutput());
        (new Process([...$git, 'tag', '-a', 'v1.1.0', '-m', 'Release v1.1.0', '-m', 'release-check: OK ' . $sha], $repo))->mustRun();

        [$code, $out] = $this->runScript('assert-release-proof', ['v1.1.0'], [], $repo);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('OK: v1.1.0 records a passing release-check for ' . $sha, $out);

        // Lightweight tag: no message, no proof.
        [$code, $out] = $this->runScript('assert-release-proof', ['v1.0.0'], [], $repo);
        self::assertSame(1, $code, $out);
    }

    public function testCleanStalePidPathsScriptKeepsLiveProcesses(): void
    {
        $this->repo = sys_get_temp_dir() . '/pqt-pids-' . bin2hex(random_bytes(4));
        $live = $this->repo . '/markers-' . getmypid();
        $dead = $this->repo . '/markers-999999999';
        mkdir($live, 0o777, true);
        mkdir($dead . '/sub', 0o777, true);
        touch($dead . '/sub/setup.done');

        [$code, $out] = $this->runScript('clean-stale-pid-paths', [$this->repo . '/markers-{pid}']);

        self::assertSame(0, $code, $out);
        self::assertStringContainsString('removed 1 path(s) of dead processes, kept 1 of running processes.', $out);
        self::assertDirectoryExists($live);
        self::assertDirectoryDoesNotExist($dead);
    }

    public function testFrontEndAndDocsGateScripts(): void
    {
        $this->repo = sys_get_temp_dir() . '/pqt-front-' . bin2hex(random_bytes(4));
        $files = [
            'templates/base.html.twig' => "{{ ux_icon('lucide:x') }}",
            'assets/app.ts' => 'export {};',
            'public/build/.vite/manifest.json' => '{"assets/app.ts": {"file": "assets/app-1.js", "css": ["assets/app-1.css"]}}',
            'public/build/assets/app-1.js' => str_repeat('j', 1200),
            'public/build/assets/app-1.css' => str_repeat('c', 800),
            'config/quality/asset-budgets.json' => '{"budgets": {"assets/app.ts": {"js": "2kB", "css": "1kB"}}}',
            'README.md' => "# App\n\n[Usage](docs/USAGE.md#gates)\n",
            'docs/USAGE.md' => "## Gates\n",
        ];
        foreach ($files as $path => $content) {
            (new Filesystem())->dumpFile($this->repo . '/' . $path, $content);
        }

        [$code, $out] = $this->runScript('check-no-inline-svg', [], [], $this->repo);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('OK: no inline SVG (1 template(s), 1 script(s), 0 allowed).', $out);

        [$code, $out] = $this->runScript('check-asset-budgets', [], [], $this->repo);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('OK   assets/app.ts js: 1.2 kB', $out);
        [$code, $out] = $this->runScript('check-asset-budgets', ['--budget=assets/app.ts:css=500B'], [], $this->repo);
        self::assertSame(1, $code, $out);
        self::assertStringContainsString('FAIL assets/app.ts css: 0.8 kB', $out);

        [$code, $out] = $this->runScript('check-doc-links', ['--check-anchors'], [], $this->repo);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('OK: doc links (2 Markdown files, no broken relative link, image or anchor).', $out);
    }

    public function testPhpWorkerResetMakefileSnippet(): void
    {
        $make = new Process(['make', '--version']);
        $make->run();
        if (!$make->isSuccessful()) {
            self::markTestSkipped('GNU make is not installed.');
        }
        $this->repo = sys_get_temp_dir() . '/pqt-make-' . bin2hex(random_bytes(4));
        $log = $this->repo . '/calls.log';
        $fs = new Filesystem();
        // Fake `docker` and compose wrappers: log every call; `ps -q` prints one container id per stack.
        $fs->dumpFile($this->repo . '/bin/docker', "#!/bin/sh\necho \"docker \$*\" >> {$log}\necho healthy\n");
        $fs->dumpFile($this->repo . '/bin/dc-dev', "#!/bin/sh\necho \"dev \$*\" >> {$log}\n[ \"\$1\" = ps ] && echo dev-id\nexit 0\n");
        $fs->dumpFile($this->repo . '/bin/dc-e2e', "#!/bin/sh\necho \"e2e \$*\" >> {$log}\n[ \"\$1\" = ps ] && echo e2e-id\nexit 0\n");
        $fs->dumpFile($this->repo . '/bin/dc-down', "#!/bin/sh\necho \"down \$*\" >> {$log}\nexit 1\n");
        $fs->chmod([$this->repo . '/bin/docker', $this->repo . '/bin/dc-dev', $this->repo . '/bin/dc-e2e', $this->repo . '/bin/dc-down'], 0o755);
        $env = ['PATH' => $this->repo . '/bin:' . getenv('PATH')];
        $snippet = self::ROOT . '/resources/make/php-worker-reset.mk';

        $process = new Process(['make', '-s', '-f', $snippet, 'php-worker-reset', 'PHP_WORKER_RESET_COMPOSE=dc-dev', 'PHP_WORKER_RESET_EXTRA_COMPOSE=dc-e2e', 'PHP_WORKER_RESET_SERVICE=app'], $this->repo, $env, null, 30.0);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame("PHP workers restarted (cache cleared): fresh Vite entrypoints.\n", $process->getOutput());
        self::assertSame(
            "dev exec -T app bin/console cache:clear --no-warmup\n"
            . "dev restart app\n"
            . "e2e exec -T app true\n"
            . "e2e exec -T app bin/console cache:clear --no-warmup\n"
            . "e2e restart app\n"
            . "dev ps -q app\n"
            . "e2e ps -q app\n"
            . "docker inspect -f {{if .State.Health}}{{.State.Health.Status}}{{else}}healthy{{end}} dev-id\n"
            . "docker inspect -f {{if .State.Health}}{{.State.Health.Status}}{{else}}healthy{{end}} e2e-id\n",
            file_get_contents($log),
        );

        // Extra stack down: only probed, never restarted.
        unlink($log);
        $process = new Process(['make', '-s', '-f', $snippet, 'php-worker-reset', 'PHP_WORKER_RESET_COMPOSE=dc-dev', 'PHP_WORKER_RESET_EXTRA_COMPOSE=dc-down'], $this->repo, $env, null, 30.0);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringNotContainsString('down restart', (string) file_get_contents($log));
        self::assertStringContainsString('down exec -T php true', (string) file_get_contents($log));

        // A container that never becomes healthy fails after the timeout.
        $fs->dumpFile($this->repo . '/bin/docker', "#!/bin/sh\necho starting\n");
        $process = new Process(['make', '-s', '-f', $snippet, 'php-worker-reset', 'PHP_WORKER_RESET_COMPOSE=dc-dev', 'PHP_WORKER_RESET_TIMEOUT=1'], $this->repo, $env, null, 30.0);
        $process->run();
        self::assertNotSame(0, $process->getExitCode());
        self::assertStringContainsString('php-worker-reset: container dev-id not healthy after 1s', $process->getErrorOutput());
    }

    public function testAssertLatestTagScriptReadsStdinOutsideGit(): void
    {
        $process = new Process([\PHP_BINARY, self::ROOT . '/bin/assert-latest-tag', '--stdin'], sys_get_temp_dir(), ['GITHUB_REF_NAME' => 'v3.0.0'], "v2.0.0\nv3.0.0\n", 30.0);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('pushed=v3.0.0 latest=v3.0.0', $process->getOutput());
    }

    public function testCoverageThresholdScript(): void
    {
        $clover = self::ROOT . '/tests/Fixtures/clover/partial.xml';

        [$code, $out] = $this->runScript('check-coverage-threshold', [$clover, '70']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Statement coverage: 75.00% (9/12)', $out);

        [$code, $out] = $this->runScript('check-coverage-threshold', [$clover], ['COVERAGE_MIN' => '80']);
        self::assertSame(1, $code);
        self::assertStringContainsString('ERROR: Statement coverage 75.00% is below threshold 80.00%', $out);
    }

    public function testModuleBoundariesScript(): void
    {
        $config = self::ROOT . '/tests/Fixtures/module-boundaries/beacon.yaml';

        [$code, $out] = $this->runScript('check-module-boundaries', ['--config=' . $config, '--root=' . self::ROOT . '/tests/Fixtures']);

        // The fixtures directory has none of Beacon's required files.
        self::assertSame(1, $code);
        self::assertStringContainsString('error: missing src/Shared/Doctrine/SqlLikeEscaper.php', $out);
    }

    /**
     * @param list<string>          $args
     * @param array<string, string> $env
     *
     * @return array{int, string}
     */
    private function runScript(string $script, array $args, array $env = [], ?string $cwd = null): array
    {
        $process = new Process([\PHP_BINARY, self::ROOT . '/bin/' . $script, ...$args], $cwd, $env, null, 30.0);
        $process->run();

        return [(int) $process->getExitCode(), $process->getOutput() . $process->getErrorOutput()];
    }

    /**
     * @param list<string> $tags
     */
    private function createRepository(array $tags): string
    {
        $this->repo = sys_get_temp_dir() . '/pqt-tags-' . bin2hex(random_bytes(4));
        mkdir($this->repo);
        $git = ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', '-c', 'commit.gpgsign=false', '-c', 'tag.gpgsign=false'];
        $commands = [['git', 'init', '--quiet'], [...$git, 'commit', '--quiet', '--allow-empty', '-m', 'init']];
        foreach ($tags as $tag) {
            $commands[] = [...$git, 'tag', $tag];
        }
        foreach ($commands as $command) {
            $process = new Process($command, $this->repo, null, null, 30.0);
            $process->mustRun();
        }

        return $this->repo;
    }
}
