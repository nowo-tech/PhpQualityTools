<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Integration;

use PHPUnit\Framework\TestCase;
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

    public function testComposerJsonDeclaresBothScripts(): void
    {
        $composer = json_decode((string) file_get_contents(self::ROOT . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($composer);
        self::assertSame(['bin/check-coverage-threshold', 'bin/check-module-boundaries'], $composer['bin'] ?? null);
        self::assertTrue(is_executable(self::ROOT . '/bin/check-coverage-threshold'));
        self::assertTrue(is_executable(self::ROOT . '/bin/check-module-boundaries'));
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
    private function runScript(string $script, array $args, array $env = []): array
    {
        $process = new Process([\PHP_BINARY, self::ROOT . '/bin/' . $script, ...$args], null, $env, null, 30.0);
        $process->run();

        return [(int) $process->getExitCode(), $process->getOutput() . $process->getErrorOutput()];
    }
}
