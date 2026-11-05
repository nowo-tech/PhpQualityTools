<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Coverage\CoverageAnnotationsCommand;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CoverageAnnotationsCommandTest extends TestCase
{
    use TempTreeTrait;

    private const CLEAN = <<<'PHP'
        <?php
        // The branch below needs a real network; covered by the e2e suite.
        // @codeCoverageIgnoreStart
        $a = 1;
        //@codeCoverageIgnoreEnd
        $b = foo(); // @codeCoverageIgnore
        /** @codeCoverageIgnore docblock annotations are not line annotations */
        function f(): void {}
        $label = '// @codeCoverageIgnore inside a string';
        $value = match ($x) { 1 => 'a', default => 'b' };
        $result = $router::match($path);
        $other = $object->match($x);
        $y = $z ? $value : null;
        $fn = static fn ($v) => match (true) { default => $v };
        PHP;

    public function testCleanSourcePasses(): void
    {
        $root = $this->makeTree([
            'src/Clean.php' => self::CLEAN,
            'src/vendor/Lib.php' => "<?php\n// @codeCoverageIgnore because vendored\n",
            'src/notes.txt' => '// @codeCoverageIgnore because text',
        ]);

        [$code, $out, $err] = $this->runCommand($root, []);

        self::assertSame(0, $code, $err);
        self::assertSame("OK: coverage annotations (1 PHP file(s)).\n", $out);
    }

    public function testReportsIgnoredAnnotationsAndUnmappableMatch(): void
    {
        $root = $this->makeTree([
            'src/Bad.php' => <<<'PHP'
                <?php
                // @codeCoverageIgnoreStart because the driver is flaky
                $a = 1;
                // @codeCoverageIgnoreEnd
                $b = foo(); // @codeCoverageIgnore network
                # @codeCoverageIgnore
                /* @codeCoverageIgnore */
                //  @codeCoverageIgnore
                $c = $x ? match ($y) { default => 1 } : null;
                $d = $x ?: match ($y) { default => 1 };
                $e = $x ?? match ($y) { default => 1 };
                $f = g(name: match ($y) { default => 1 });
                PHP,
        ]);

        [$code, $out, $err] = $this->runCommand($root, []);

        self::assertSame(1, $code);
        self::assertSame('', $out);
        $fix = ' is ignored by php-code-coverage (write exactly "// @codeCoverageIgnore[Start|End]", the reason on the line above)';
        $match = ' cannot be mapped by the coverage driver (assign the match to a variable first)';
        self::assertSame(
            'error: src/Bad.php:2 "// @codeCoverageIgnoreStart because the driver is flaky"' . $fix . "\n"
            . 'error: src/Bad.php:5 "// @codeCoverageIgnore network"' . $fix . "\n"
            . 'error: src/Bad.php:6 "# @codeCoverageIgnore"' . $fix . "\n"
            . 'error: src/Bad.php:7 "/* @codeCoverageIgnore */"' . $fix . "\n"
            . 'error: src/Bad.php:8 "//  @codeCoverageIgnore"' . $fix . "\n"
            . 'error: src/Bad.php:9 match() after "?"' . $match . "\n"
            . 'error: src/Bad.php:10 match() after ":"' . $match . "\n"
            . 'error: src/Bad.php:11 match() after "??"' . $match . "\n"
            . 'error: src/Bad.php:12 match() after ":"' . $match . "\n"
            . "Coverage annotations: 9 violation(s).\n",
            $err,
        );

        [$code, , $err] = $this->runCommand($root, ['--no-match']);
        self::assertSame(1, $code);
        self::assertStringNotContainsString('match()', $err);
        self::assertStringContainsString('Coverage annotations: 5 violation(s).', $err);
    }

    public function testLongCommentsAreShortenedAndMatchAtEndOfFile(): void
    {
        $root = $this->makeTree([
            'lib/A.php' => "<?php\n// @codeCoverageIgnore " . str_repeat('very long reason ', 10) . "\n\$a = \$b ? match",
        ]);

        [$code, , $err] = $this->runCommand($root, ['--paths=lib']);

        self::assertSame(1, $code);
        self::assertStringContainsString('error: lib/A.php:2 "// @codeCoverageIgnore very long reason very long reason very long reason ver..." is ignored', $err);
        self::assertStringContainsString('Coverage annotations: 1 violation(s).', $err);
    }

    public function testPathsExcludesAndRoot(): void
    {
        $bad = "<?php\n// @codeCoverageIgnore why\n";
        $root = $this->makeTree([
            'src/A.php' => $bad,
            'src/Generated/B.php' => $bad,
            'src/Legacy/C.php' => $bad,
            'app/D.php' => $bad,
            'app/E.php' => "<?php\n",
        ]);

        [$code, $out, $err] = $this->runCommand($root, ['--exclude=src/Generated/', '--exclude=src/L*', '--exclude=./src/A.php']);
        self::assertSame(0, $code, $err);
        self::assertSame("OK: coverage annotations (0 PHP file(s)).\n", $out);

        [$code, , $err] = $this->runCommand(\dirname($root), ['--root=' . basename($root), '--paths=./app/', '--paths=src/A.php']);
        self::assertSame(1, $code);
        self::assertStringContainsString('error: app/D.php:2', $err);
        self::assertStringContainsString('error: src/A.php:2', $err);
        self::assertStringNotContainsString('Generated', $err);

        // No src/ and no --paths: nothing to scan.
        [$code, $out] = $this->runCommand('/', ['--root=' . $root . '/app/']);
        self::assertSame(0, $code);
        self::assertSame("OK: coverage annotations (0 PHP file(s)).\n", $out);
    }

    public function testHelpAndUsageErrors(): void
    {
        $root = $this->makeTree(['src/A.php' => "<?php\n"]);

        [$code, $out] = $this->runCommand($root, ['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: check-coverage-annotations', $out);

        foreach ([
            ['--bogus=1', 'Unknown option --bogus=1'],
            ['--paths', 'Unknown or empty option --paths'],
            ['--exclude=', 'Unknown or empty option --exclude='],
            ['--paths=missing', 'path not found: missing'],
            ['--root=missing', 'root directory not found: ' . $root . '/missing'],
        ] as [$arg, $message]) {
            [$code, , $err] = $this->runCommand($root, [$arg]);
            self::assertSame(2, $code, $arg);
            self::assertStringContainsString($message, $err, $arg);
        }
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
        $code = (new CoverageAnnotationsCommand($stdout, $stderr, $cwd))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
