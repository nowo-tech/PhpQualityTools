<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Twig\TwigExtraCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TwigExtraCommand::class)]
final class TwigExtraCommandTest extends TestCase
{
    use TempTreeTrait;

    private const READY = '{"require":{"twig/extra-bundle":"^3.0","twig/string-extra":"^3.0"}}';

    public function testABundleWithoutTemplatesIsNotConcerned(): void
    {
        $root = $this->makeTree(['composer.json' => '{}', 'src/Foo.php' => '<?php']);

        [$code, $out] = $this->runCommand($root, []);

        self::assertSame(0, $code);
        self::assertSame("check-twig-extra: Twig does not apply (no templates) - OK\n", $out);
    }

    public function testTemplatesUnderAnyOfTheKnownRootsMakeTwigApply(): void
    {
        foreach (['src/Resources/views/a.html.twig', 'templates/a.html.twig', 'Resources/views/a.html.twig'] as $template) {
            $root = $this->makeTree(['composer.json' => '{}', $template => '']);

            [$code, , $err] = $this->runCommand($root, []);

            self::assertSame(1, $code, $template);
            self::assertStringContainsString('twig/extra-bundle missing from package composer.json', $err);
        }
    }

    public function testAReadyBundleAndDemoPass(): void
    {
        $root = $this->makeTree([
            'composer.json' => self::READY,
            'src/Resources/views/a.html.twig' => '{{ x }}',
            'demo/symfony8/composer.json' => '{"require":{"twig/extra-bundle":"^3.0"}}',
            'demo/symfony8/config/bundles.php' => "<?php\nreturn [Symfony\\Bundle\\TwigBundle\\TwigBundle::class => ['all' => true], Twig\\Extra\\TwigExtraBundle\\TwigExtraBundle::class => ['all' => true]];\n",
            'demo/other/sub/composer.json' => '{"require":{"twig/extra-bundle":"^3.0"}}',
            'demo/other/sub/config/bundles.php' => "<?php\nreturn ['Twig\\\\Extra\\\\TwigExtraBundle\\\\TwigExtraBundle' => ['all' => true], 'TwigBundle' => []];\n",
            // Demos that never boot Twig are skipped.
            'demo/api/composer.json' => '{}',
            'demo/api/config/bundles.php' => '<?php return [];',
            'demo/bare/composer.json' => '{}',
        ]);

        [$code, $out, $err] = $this->runCommand($root, []);

        self::assertSame(0, $code, $err);
        self::assertSame("check-twig-extra: OK\n", $out);
    }

    public function testMissingPackagesInThePackageAndInTheDemoAreReported(): void
    {
        $root = $this->makeTree([
            'composer.json' => '{"require":{"twig/extra-bundle":"^3.0"}}',
            'src/Resources/views/a.html.twig' => '',
            'demo/symfony8/composer.json' => '{}',
            'demo/symfony8/config/bundles.php' => "<?php\nreturn [Symfony\\Bundle\\TwigBundle\\TwigBundle::class => ['all' => true]];\n",
        ]);

        [$code, , $err] = $this->runCommand($root, []);

        self::assertSame(1, $code);
        self::assertSame(
            "ERROR: twig/string-extra missing from package composer.json require (REQ-TWIG-004 baseline)\n"
            . "ERROR: demo/symfony8/composer.json missing twig/extra-bundle (REQ-TWIG-004)\n"
            . "ERROR: demo/symfony8/config/bundles.php missing TwigExtraBundle (REQ-TWIG-004)\n",
            $err,
        );
    }

    public function testAMissingPackageComposerJsonCountsAsMissingPackages(): void
    {
        $root = $this->makeTree(['templates/a.html.twig' => '']);

        [$code, , $err] = $this->runCommand($root, []);

        self::assertSame(1, $code);
        self::assertStringContainsString('twig/extra-bundle missing', $err);
        self::assertStringContainsString('twig/string-extra missing', $err);
    }

    public function testForbidRawFailsOnTheFilterOutsideCommentsOnly(): void
    {
        $root = $this->makeTree([
            'composer.json' => self::READY,
            'src/Resources/views/ok.html.twig' => "{# the |raw filter is banned #}\n{{ html|sanitize }}\n{# multi\nline |raw #}",
            'src/Resources/views/bad.html.twig' => "{{ html | raw }}\n",
            'src/Resources/views/rawness.html.twig' => '{{ x|rawurl }}',
        ]);

        [$code] = $this->runCommand($root, []);
        self::assertSame(0, $code);

        [$code, , $err] = $this->runCommand($root, ['--forbid-raw=./src/Resources/views/']);
        self::assertSame(1, $code);
        self::assertSame("ERROR: |raw in src/Resources/views/bad.html.twig (print HTML through a sanitising filter instead)\n", $err);

        [$code] = $this->runCommand($root, ['--forbid-raw=missing']);
        self::assertSame(0, $code);
    }

    public function testHelpAndUsageErrors(): void
    {
        $root = $this->makeTree(['composer.json' => '{}']);

        [$code, $out] = $this->runCommand($root, ['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: check-twig-extra', $out);

        foreach ([
            ['--bogus=1', 'Unknown option --bogus=1'],
            ['--root', 'Unknown or empty option --root'],
            ['--root=missing', 'root directory not found: ' . $root . '/missing'],
        ] as [$arg, $message]) {
            [$code, , $err] = $this->runCommand($root, [$arg]);
            self::assertSame(2, $code, $arg);
            self::assertStringContainsString($message, $err, $arg);
        }

        [$code, $out] = $this->runCommand(\dirname($root), ['--root=' . basename($root)]);
        self::assertSame(0, $code);
        self::assertStringContainsString('does not apply', $out);
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
        $code = (new TwigExtraCommand($stdout, $stderr, $cwd))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
