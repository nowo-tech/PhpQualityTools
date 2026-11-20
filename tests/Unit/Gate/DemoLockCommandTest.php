<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\DemoLock\DemoLockCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DemoLockCommand::class)]
final class DemoLockCommandTest extends TestCase
{
    use TempTreeTrait;

    private const COMPOSER = '{"name":"nowo-tech/x","require":{"php":">=8.2","ext-json":"*","nowo-tech/transaction-doctrine":"^1.5","symfony/framework-bundle":"^7.4 || ^8.0"}}';

    /**
     * @param array<string, string> $packages name => version
     */
    private function lock(array $packages): string
    {
        $list = [];
        foreach ($packages as $name => $version) {
            $list[] = ['name' => $name, 'version' => $version];
        }

        return json_encode(['packages' => $list], \JSON_THROW_ON_ERROR);
    }

    public function testFreshLocksPass(): void
    {
        $root = $this->makeTree([
            'composer.json' => self::COMPOSER,
            'demo/symfony7/composer.json' => '{}',
            'demo/symfony7/composer.lock' => $this->lock(['nowo-tech/transaction-doctrine' => 'v1.9.1', 'symfony/framework-bundle' => 'v7.4.23']),
            'demo/symfony8/composer.json' => '{}',
            'demo/symfony8/composer.lock' => $this->lock(['nowo-tech/transaction-doctrine' => '1.5.0', 'symfony/framework-bundle' => 'v8.1.0']),
        ]);

        [$code, $out, $err] = $this->runCommand($root, []);

        self::assertSame(0, $code, $err);
        self::assertSame("OK: 2 demo lock(s) satisfy 2 requirement(s).\n", $out);
    }

    public function testStaleAndMissingRequirementsAreReported(): void
    {
        $root = $this->makeTree([
            'composer.json' => self::COMPOSER,
            'demo/symfony7/composer.json' => '{}',
            'demo/symfony7/composer.lock' => $this->lock(['nowo-tech/transaction-doctrine' => 'v1.4.0', 'symfony/framework-bundle' => 'v7.4.23']),
            'demo/symfony8/composer.json' => '{}',
            'demo/symfony8/composer.lock' => $this->lock(['nowo-tech/transaction-doctrine' => 'dev-main']),
        ]);

        [$code, $out, $err] = $this->runCommand($root, []);

        self::assertSame(1, $code);
        self::assertSame('', $out);
        self::assertSame(
            "STALE demo/symfony7/composer.lock: nowo-tech/transaction-doctrine ^1.5 -> v1.4.0\n"
            . "STALE demo/symfony8/composer.lock: symfony/framework-bundle ^7.4 || ^8.0 -> not installed\n",
            $err,
        );
    }

    public function testOnlyRestrictsTheRequirementsBothFormsAndPatternsAdd(): void
    {
        $root = $this->makeTree([
            'composer.json' => self::COMPOSER,
            'demo/a/composer.json' => '{}',
            'demo/a/composer.lock' => $this->lock(['nowo-tech/transaction-doctrine' => 'v1.9.0']),
        ]);

        [$code, $out] = $this->runCommand($root, ['--only', 'nowo-tech/*']);
        self::assertSame(0, $code);
        self::assertSame("OK: 1 demo lock(s) satisfy 1 requirement(s).\n", $out);

        [$code, $out] = $this->runCommand($root, ['--only=nowo-tech/*', '--only=symfony/*']);
        self::assertSame(1, $code, $out);

        [$code, $out] = $this->runCommand($root, ['--only=none/*']);
        self::assertSame(0, $code);
        self::assertSame("OK: 1 demo lock(s) satisfy 0 requirement(s).\n", $out);
    }

    public function testADemoWithoutALockPassesAndTheBundleDirectoryIsAnArgument(): void
    {
        $root = $this->makeTree([
            'composer.json' => self::COMPOSER,
            'demo/symfony7/composer.json' => '{}',
        ]);

        [$code, $out] = $this->runCommand(\dirname($root), [basename($root) . '/']);
        self::assertSame(0, $code);
        self::assertSame("OK demo/symfony7/composer.json: no composer.lock (resolved at install)\nOK: 0 demo lock(s) satisfy 2 requirement(s).\n", $out);

        [$code] = $this->runCommand('/', [$root]);
        self::assertSame(0, $code);
    }

    public function testInvalidJsonIsAUsageErrorNotAPass(): void
    {
        $cases = [
            'composer.json' => ['composer.json' => 'not json'],
            'composer.json array' => ['composer.json' => '[1,2]'],
            'require' => ['composer.json' => '{"require":"x"}'],
            'lock' => ['composer.json' => self::COMPOSER, 'demo/a/composer.json' => '{}', 'demo/a/composer.lock' => 'not json'],
        ];
        foreach ($cases as $label => $files) {
            [$code, $out, $err] = $this->runCommand($this->makeTree($files), []);

            self::assertSame(2, $code, $label);
            self::assertSame('', $out, $label);
            self::assertStringContainsString('ERROR:', $err, $label);
        }

        [, , $err] = $this->runCommand($this->makeTree(['composer.json' => '{']), []);
        self::assertStringContainsString('composer.json is not valid JSON', $err);
        [, , $err] = $this->runCommand($this->makeTree($cases['lock']), []);
        self::assertStringContainsString('demo/a/composer.lock is not valid JSON', $err);
    }

    public function testLockEntriesThatAreNotPackagesAreIgnored(): void
    {
        $root = $this->makeTree([
            'composer.json' => '{"require":{"nowo-tech/a":"^1.0","nowo-tech/b":1}}',
            'demo/a/composer.json' => '{}',
            'demo/a/composer.lock' => '{"packages":[1,{"name":"nowo-tech/a"},{"name":"nowo-tech/a","version":"1.2.0"}]}',
            'demo/b/composer.json' => '{}',
            'demo/b/composer.lock' => '{"packages":"x"}',
        ]);

        [$code, , $err] = $this->runCommand($root, []);

        self::assertSame(1, $code);
        self::assertSame("STALE demo/b/composer.lock: nowo-tech/a ^1.0 -> not installed\n", $err);
    }

    public function testHelpAndUsageErrors(): void
    {
        $root = $this->makeTree(['composer.json' => self::COMPOSER]);

        [$code, $out] = $this->runCommand($root, ['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: check-demo-lock', $out);

        foreach ([
            [['--only'], '--only needs a pattern'],
            [['--only='], '--only needs a non-empty pattern'],
            [['--only', ''], '--only needs a non-empty pattern'],
            [['--only=nowo-tech/*', '--only='], '--only needs a non-empty pattern'],
            [['--bogus'], 'Unknown option --bogus'],
            [['a', 'b'], 'Only one bundle directory is accepted'],
        ] as [$args, $message]) {
            [$code, , $err] = $this->runCommand($root, $args);
            self::assertSame(2, $code, implode(' ', $args));
            self::assertStringContainsString($message, $err);
        }

        $empty = $this->makeTree(['README.md' => '']);
        [$code, , $err] = $this->runCommand($empty, []);
        self::assertSame(2, $code);
        self::assertStringContainsString('composer.json not found in ' . $empty, $err);
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string, string}
     */
    private function runCommand(string $cwd, array $args): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        $code = (new DemoLockCommand($stdout, $stderr, $cwd))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
