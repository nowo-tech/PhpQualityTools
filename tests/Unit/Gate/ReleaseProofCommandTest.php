<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Release\ReleaseProofCommand;
use NowoTech\PhpQualityTools\Process\CommandRunnerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ReleaseProofCommandTest extends TestCase
{
    private const SHA = '3270f7c0ab29ed4137a84ef682aba8469a8c5bec';

    private ?string $outputFile = null;

    protected function tearDown(): void
    {
        if (null !== $this->outputFile && is_file($this->outputFile)) {
            unlink($this->outputFile);
        }
    }

    public function testProofForTheTaggedCommitPasses(): void
    {
        [$code, $out] = $this->execute(['v1.2.3'], ['Release v1.2.3', '', 'release-check: OK ' . self::SHA]);

        self::assertSame(0, $code);
        self::assertStringContainsString('OK: v1.2.3 records a passing release-check', $out);
    }

    public function testMissingOrForeignProofFails(): void
    {
        [$code, , $err] = $this->execute(['v1.2.3'], ['Release v1.2.3', 'release-check: OK 0000000000000000000000000000000000000000']);
        self::assertSame(1, $code);
        self::assertStringContainsString('has no "release-check: OK ' . self::SHA . '" line', $err);

        [$code] = $this->execute(['refs/tags/v1.2.3'], []);
        self::assertSame(1, $code);
    }

    public function testGithubOutputWritesTheDecision(): void
    {
        $this->outputFile = (string) tempnam(sys_get_temp_dir(), 'gh-out-');

        [$code, $out] = $this->execute(['--github-output'], ['Release'], ['GITHUB_REF_NAME' => 'v1.2.3', 'GITHUB_OUTPUT' => $this->outputFile]);
        self::assertSame(0, $code);
        self::assertStringContainsString('::error title=No release-check proof::', $out);
        self::assertSame("proof=false\n", file_get_contents($this->outputFile));

        [$code] = $this->execute(['v1.2.3', '--github-output'], ['release-check: OK ' . self::SHA], ['GITHUB_OUTPUT' => $this->outputFile]);
        self::assertSame(0, $code);
        self::assertSame("proof=false\nproof=true\n", file_get_contents($this->outputFile));

        [$code, , $err] = $this->execute(['v1.2.3', '--github-output'], [], ['GITHUB_OUTPUT' => sys_get_temp_dir()]);
        self::assertSame(2, $code);
        self::assertStringContainsString('cannot write to GITHUB_OUTPUT', $err);
    }

    public function testExplicitSkipIsLoggedOnlyWhenAllowed(): void
    {
        [$code, $out] = $this->execute(['v1.2.3', '--allow-skip'], [], ['RELEASE_GATE' => 'skip']);
        self::assertSame(0, $code);
        self::assertStringContainsString('::warning title=Release gate skipped::', $out);

        [$code] = $this->execute(['v1.2.3'], [], ['RELEASE_GATE' => 'skip']);
        self::assertSame(1, $code);
    }

    public function testUsageAndGitErrors(): void
    {
        [$code, $out] = $this->execute(['--help'], []);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: assert-release-proof', $out);

        self::assertSame(2, $this->execute(['--nope'], [])[0]);
        self::assertSame(2, $this->execute(['a', 'b'], [])[0]);
        self::assertSame(2, $this->execute([], [])[0]);
        self::assertSame(2, $this->execute(['bad tag'], [])[0]);
        self::assertSame(2, $this->execute(['v1.2.3', '--github-output'], [])[0]);

        [$code, , $err] = $this->execute(['v1.2.3'], [], [], gitFails: true);
        self::assertSame(2, $code);
        self::assertStringContainsString('cannot read tag v1.2.3', $err);
    }

    /**
     * @param list<string>          $args
     * @param list<string>          $tagMessage
     * @param array<string, string> $env
     *
     * @return array{int, string, string}
     */
    private function execute(array $args, array $tagMessage, array $env = [], bool $gitFails = false): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $runner = new class($tagMessage, $gitFails) implements CommandRunnerInterface {
            /**
             * @param list<string> $tagMessage
             */
            public function __construct(private readonly array $tagMessage, private readonly bool $gitFails)
            {
            }

            public function run(string $command): array
            {
                if ($this->gitFails) {
                    return ['exitCode' => 128, 'output' => ['fatal: bad revision']];
                }

                return ['exitCode' => 0, 'output' => str_starts_with($command, 'git rev-list') ? ['3270f7c0ab29ed4137a84ef682aba8469a8c5bec'] : $this->tagMessage];
            }
        };

        $code = (new ReleaseProofCommand($stdout, $stderr, $env, $runner))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
