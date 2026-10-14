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
        self::assertSame(['bin/assert-latest-tag', 'bin/check-coverage-threshold', 'bin/check-module-boundaries'], $composer['bin'] ?? null);
        self::assertTrue(is_executable(self::ROOT . '/bin/assert-latest-tag'));
        self::assertTrue(is_executable(self::ROOT . '/bin/check-coverage-threshold'));
        self::assertTrue(is_executable(self::ROOT . '/bin/check-module-boundaries'));
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
