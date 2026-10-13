<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ModuleBoundaryCommand;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ModuleBoundaryCommandTest extends TestCase
{
    use TempTreeTrait;

    public function testDefaultConfigUnderRootPasses(): void
    {
        $root = $this->makeTree([
            ...ModuleBoundaryCheckerTest::beaconTree(),
            'config/quality/module-boundaries.yaml' => (string) file_get_contents(ModuleBoundaryCheckerTest::BEACON_CONFIG),
        ]);

        [$code, $out, $err] = $this->execute([], $root);

        self::assertSame(0, $code, $err);
        self::assertSame("OK: module boundaries (7 module(s), 17 PHP file(s) scanned, 3 edge rule(s), 18 extra rule(s)).\n", $out);
    }

    public function testViolationsExitOne(): void
    {
        $root = $this->makeTree([
            'app/src/Shared/Util/Clock.php' => "<?php\nuse App\\Project\\Entity\\Project;\n",
            'app/config/quality/module-boundaries.php' => "<?php\nreturn ['modules' => ['Shared' => ['namespace' => 'App\\\\Shared', 'path' => 'src/Shared'], 'Project' => 'App\\\\Project'],"
                . " 'forbidden_edges' => [['from' => 'Shared', 'to' => 'Project']], 'rules' => [['type' => 'require_path', 'path' => ['a.php', 'b.php']]]];\n",
        ]);

        [$code, $out, $err] = $this->execute(['--root=app'], $root);

        self::assertSame(1, $code);
        self::assertSame('', $out);
        self::assertSame(
            "error: src/Shared/Util/Clock.php:2 imports App\\Project\\Entity\\Project (Shared -> Project is forbidden).\n"
            . "error: Required path(s) missing:\n  a.php\n  b.php\n"
            . "Module boundaries: 2 violation(s).\n",
            $err,
        );
    }

    public function testExplicitConfigRelativeToCwdAndAbsoluteRoot(): void
    {
        $root = $this->makeTree([
            'tree/src/A.php' => "<?php\n",
            'rules.yml' => "rules:\n  - { type: require_path, path: src/A.php }\n",
        ]);

        [$code, $out] = $this->execute(['--config=rules.yml', '--root=' . $root . '/tree'], $root);

        self::assertSame(0, $code);
        self::assertStringContainsString('0 module(s), 0 PHP file(s) scanned, 0 edge rule(s), 1 extra rule(s)', $out);
    }

    public function testErrors(): void
    {
        $root = $this->makeTree(['bad.yaml' => "colour: red\n"]);

        [$code, , $err] = $this->execute(['--verbose'], $root);
        self::assertSame(2, $code);
        self::assertStringContainsString('Unknown argument --verbose', $err);

        [$code, , $err] = $this->execute(['--root=missing'], $root);
        self::assertSame(2, $code);
        self::assertStringContainsString('root directory not found', $err);

        [$code, , $err] = $this->execute([], $root);
        self::assertSame(2, $code);
        self::assertStringContainsString('no config found', $err);

        [$code, , $err] = $this->execute(['--config=bad.yaml'], $root);
        self::assertSame(2, $code);
        self::assertStringContainsString('Unknown key(s): colour', $err);

        [$code, $out] = $this->execute(['-h'], $root);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: check-module-boundaries', $out);
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string, string}
     */
    private function execute(array $args, string $cwd): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $code = (new ModuleBoundaryCommand($stdout, $stderr, $cwd))->run($args);

        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
