<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Release\LatestTagCommand;
use NowoTech\PhpQualityTools\Process\CommandRunnerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class LatestTagCommandTest extends TestCase
{
    private const TAGS = ['v1.9.0', 'v1.10.0', 'v1.2.3', 'v2.0.0-rc1', 'v1.10', 'nightly', 'v01.99.0'];

    /** @var list<string> */
    private array $commands = [];

    private ?string $outputFile = null;

    protected function tearDown(): void
    {
        if (null !== $this->outputFile && is_file($this->outputFile)) {
            unlink($this->outputFile);
        }
    }

    /**
     * @param list<string> $tags
     */
    #[DataProvider('latestProvider')]
    public function testLatest(array $tags, string $prefix, ?string $expected): void
    {
        self::assertSame($expected, LatestTagCommand::latest($tags, $prefix));
    }

    /**
     * @return iterable<string, array{list<string>, string, string|null}>
     */
    public static function latestProvider(): iterable
    {
        yield 'semver order, not string order' => [self::TAGS, 'v', 'v1.10.0'];
        yield 'major wins' => [['v1.99.99', ' v2.0.0 ', 'v1.100.0'], 'v', 'v2.0.0'];
        yield 'patch' => [['v1.0.1', 'v1.0.10', 'v1.0.9'], 'v', 'v1.0.10'];
        yield 'no release tag' => [['nightly', 'v2.0.0-rc1'], 'v', null];
        yield 'empty list' => [[], 'v', null];
        yield 'bare tags' => [['1.2.0', 'v9.0.0', '1.10.0'], '', '1.10.0'];
        yield 'custom prefix' => [['api/v1.0.0', 'api/v1.1.0', 'v9.0.0'], 'api/v', 'api/v1.1.0'];
    }

    public function testLatestTagPassesFromGit(): void
    {
        [$code, $out, $err] = $this->execute(['v1.10.0'], self::TAGS);

        self::assertSame(0, $code);
        self::assertStringContainsString('pushed=v1.10.0 latest=v1.10.0', $out);
        self::assertStringContainsString('OK: v1.10.0 is the latest release tag.', $out);
        self::assertSame('', $err);
        self::assertSame(["git tag --list 'v*'"], $this->commands);
    }

    public function testOlderTagFails(): void
    {
        [$code, $out, $err] = $this->execute(['v1.9.0'], self::TAGS);

        self::assertSame(1, $code);
        self::assertStringContainsString('pushed=v1.9.0 latest=v1.10.0', $out);
        self::assertStringContainsString('ERROR: v1.9.0 is not the latest release (v1.10.0).', $err);
    }

    public function testNonReleaseTagFails(): void
    {
        [$code, , $err] = $this->execute(['v2.0.0-rc1'], self::TAGS);

        self::assertSame(1, $code);
        self::assertStringContainsString('ERROR: v2.0.0-rc1 is not a release tag (vMAJOR.MINOR.PATCH).', $err);
    }

    public function testNoReleaseTagAtAll(): void
    {
        [$code, $out, $err] = $this->execute(['v1.0.0'], []);

        self::assertSame(1, $code);
        self::assertStringContainsString('latest=<none>', $out);
        self::assertStringContainsString('is not the latest release (none)', $err);
    }

    public function testTagFromGithubRefNameAndPrefix(): void
    {
        [$code, , $err] = $this->execute(['--prefix=release-'], ['release-1.0.0', 'release-1.1.0'], ['GITHUB_REF_NAME' => 'refs/tags/release-1.1.0']);

        self::assertSame(0, $code, $err);
        self::assertSame(["git tag --list 'release-*'"], $this->commands);
    }

    public function testFetchRunsBeforeListing(): void
    {
        [$code] = $this->execute(['v1.10.0', '--fetch'], self::TAGS);
        self::assertSame(0, $code);
        self::assertSame(["git fetch --tags --force --quiet 'origin'", "git tag --list 'v*'"], $this->commands);

        $this->commands = [];
        $this->execute(['v1.10.0', '--fetch=upstream'], self::TAGS);
        self::assertSame(["git fetch --tags --force --quiet 'upstream'", "git tag --list 'v*'"], $this->commands);
    }

    public function testGitFailures(): void
    {
        [$code, , $err] = $this->execute(['v1.0.0'], self::TAGS, [], ['git fetch' => ['fatal: no remote']], ['--fetch']);
        self::assertSame(2, $code);
        self::assertStringContainsString('ERROR: git fetch --tags failed', $err);
        self::assertStringContainsString('fatal: no remote', $err);

        [$code, , $err] = $this->execute(['v1.0.0'], self::TAGS, [], ['git tag' => []]);
        self::assertSame(2, $code);
        self::assertStringContainsString('ERROR: git tag --list failed', $err);
    }

    public function testStdin(): void
    {
        [$code, $out] = $this->execute(['v1.10.0', '--stdin'], null, [], [], [], "v1.2.0\r\nv1.10.0\n\nv1.9.0\n");

        self::assertSame(0, $code);
        self::assertStringContainsString('latest=v1.10.0', $out);
        self::assertSame([], $this->commands);

        [$code, $out] = $this->execute(['v1.0.0', '--stdin'], null, [], [], [], null);
        self::assertSame(1, $code);
        self::assertStringContainsString('latest=<none>', $out);
    }

    public function testGithubOutputWritesDecisionAndExitsZero(): void
    {
        $file = $this->outputFile();
        file_put_contents($file, "previous=1\n");

        [$code, $out, $err] = $this->execute(['--github-output'], self::TAGS, ['GITHUB_REF_NAME' => 'v1.2.3', 'GITHUB_OUTPUT' => $file]);
        self::assertSame(0, $code);
        self::assertSame('', $err);
        self::assertStringContainsString('::notice title=Deploy skipped::v1.2.3 is not the latest release (v1.10.0), skipping deploy', $out);
        self::assertSame("previous=1\ndeploy=false\nlatest=v1.10.0\n", file_get_contents($file));

        file_put_contents($file, '');
        [$code] = $this->execute(['--github-output', 'v1.10.0'], self::TAGS, ['GITHUB_OUTPUT' => $file]);
        self::assertSame(0, $code);
        self::assertSame("deploy=true\nlatest=v1.10.0\n", file_get_contents($file));

        file_put_contents($file, '');
        [$code] = $this->execute(['--github-output', 'v1.0.0'], [], ['GITHUB_OUTPUT' => $file]);
        self::assertSame(0, $code);
        self::assertSame("deploy=false\nlatest=\n", file_get_contents($file));
    }

    public function testGithubOutputNotWritable(): void
    {
        [$code, , $err] = $this->execute(['--github-output', 'v1.10.0'], self::TAGS, ['GITHUB_OUTPUT' => sys_get_temp_dir()]);

        self::assertSame(2, $code);
        self::assertStringContainsString('ERROR: cannot write to GITHUB_OUTPUT', $err);
    }

    /**
     * @param list<string>          $args
     * @param array<string, string> $env
     */
    #[DataProvider('usageProvider')]
    public function testUsageErrors(array $args, array $env, string $expected): void
    {
        [$code, , $err] = $this->execute($args, self::TAGS, $env);

        self::assertSame(2, $code);
        self::assertStringContainsString($expected, $err);
        self::assertStringContainsString('Usage: assert-latest-tag', $err);
        self::assertSame([], $this->commands);
    }

    /**
     * @return iterable<string, array{list<string>, array<string, string>, string}>
     */
    public static function usageProvider(): iterable
    {
        yield 'no tag' => [[], [], 'No tag given and GITHUB_REF_NAME is unset.'];
        yield 'empty ref name' => [[], ['GITHUB_REF_NAME' => ''], 'No tag given'];
        yield 'two tags' => [['v1.0.0', 'v2.0.0'], [], 'Expected at most one <tag>.'];
        yield 'unknown option' => [['v1.0.0', '--verbose'], [], 'Unknown option --verbose'];
        yield 'bad prefix' => [['v1.0.0', '--prefix=v*'], [], 'Invalid --prefix v*'];
        yield 'empty fetch remote' => [['v1.0.0', '--fetch='], [], '--fetch= needs a remote name.'];
        yield 'fetch with stdin' => [['v1.0.0', '--fetch', '--stdin'], [], '--fetch cannot be combined with --stdin.'];
        yield 'github output without env' => [['v1.0.0', '--github-output'], [], '--github-output needs the GITHUB_OUTPUT'];
    }

    public function testDefaultRunnerIsNotNeededWithStdin(): void
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        $stdin = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        self::assertIsResource($stdin);
        fwrite($stdin, "v1.0.0\n");
        rewind($stdin);

        self::assertSame(0, (new LatestTagCommand($stdout, $stderr, ['GITHUB_REF_NAME' => 'v1.0.0'], null, $stdin))->run(['--stdin']));
    }

    public function testHelp(): void
    {
        [$code, $out] = $this->execute(['--help'], self::TAGS);

        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: assert-latest-tag', $out);
        self::assertSame([], $this->commands);
    }

    /**
     * @param list<string>                $args
     * @param list<string>|null           $tags     Output of `git tag --list` (null: never expected)
     * @param array<string, string>       $env
     * @param array<string, list<string>> $failures Command prefix => output of a failing run
     * @param list<string>                $extra    Arguments appended to $args
     *
     * @return array{int, string, string}
     */
    private function execute(array $args, ?array $tags, array $env = [], array $failures = [], array $extra = [], ?string $stdinContent = ''): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        $stdin = null;
        if (null !== $stdinContent) {
            $stdin = fopen('php://memory', 'w+');
            self::assertIsResource($stdin);
            fwrite($stdin, $stdinContent);
            rewind($stdin);
        }

        /** @var \ArrayObject<int, string> $log */
        $log = new \ArrayObject();
        $runner = new class($log, $tags ?? [], $failures) implements CommandRunnerInterface {
            /**
             * @param \ArrayObject<int, string>   $log
             * @param list<string>                $tags
             * @param array<string, list<string>> $failures
             */
            public function __construct(private readonly \ArrayObject $log, private readonly array $tags, private readonly array $failures)
            {
            }

            public function run(string $command): array
            {
                $this->log->append($command);
                foreach ($this->failures as $prefix => $output) {
                    if (str_starts_with($command, $prefix)) {
                        return ['exitCode' => 128, 'output' => $output];
                    }
                }

                return ['exitCode' => 0, 'output' => str_starts_with($command, 'git tag') ? $this->tags : []];
            }
        };

        $code = (new LatestTagCommand($stdout, $stderr, $env, $runner, $stdin))->run([...$args, ...$extra]);
        $this->commands = [...$this->commands, ...$log->getArrayCopy()];

        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }

    private function outputFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'gh-output-');
        self::assertIsString($file);

        return $this->outputFile = $file;
    }
}
