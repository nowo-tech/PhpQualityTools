<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Svg\NoInlineSvgCommand;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class NoInlineSvgCommandTest extends TestCase
{
    use TempTreeTrait;

    private const SPRITE = 'templates/bundles/NelmioApiDocBundle/SwaggerUi/index.html.twig';

    public function testCleanProjectPasses(): void
    {
        $root = $this->makeTree([
            'templates/base.html.twig' => "{{ ux_icon('lucide:x') }}\n<span>svg is a word</span>",
            self::SPRITE => "<svg xmlns=\"http://www.w3.org/2000/svg\">\n<symbol id=\"locked\"/></svg>",
            'assets/controllers/menu_controller.ts' => "const icon = this.iconTarget.content.cloneNode(true);\n",
            'assets/controllers/menu_controller.test.ts' => "document.body.innerHTML = '<svg></svg>';\n",
            'assets/controllers/menu.spec.ts' => "'<path d=\"M0\"/>'\n",
            'assets/types/global.d.ts' => "// <svg> in a comment of a declaration file\n",
            'assets/node_modules/lib/index.ts' => "'<svg>'\n",
            'assets/styles/app.scss' => "/* <svg> */\n",
        ]);

        [$code, $out, $err] = $this->runCommand($root, ['--allow=' . self::SPRITE]);

        self::assertSame(0, $code, $err);
        self::assertSame("OK: no inline SVG (2 template(s), 1 script(s), 1 allowed).\n", $out);
    }

    public function testReportsTemplatesAndScriptsWithLineNumbers(): void
    {
        $root = $this->makeTree([
            'templates/a.html.twig' => "<div>\n  <SVG viewBox=\"0 0 1 1\"></SVG>\n</div>",
            'templates/b.html.twig' => '<svg/>',
            'templates/c.html.twig' => '<svgx>not svg</svgx>',
            'assets/controllers/chart_controller.ts' => "const a = 1;\nconst b = 2;\nel.innerHTML = `<polyline points=\"0,0\"/>`;\n",
            'assets/controllers/ok_controller.ts' => "const pathname = location.pathname; // <pathname> is not a path element\n",
        ]);

        [$code, $out, $err] = $this->runCommand($root, []);

        self::assertSame(1, $code);
        self::assertSame('', $out);
        self::assertSame(
            "error: inline <svg> in templates/a.html.twig:2 (render the icon through the icon component, e.g. ux_icon())\n"
            . "error: inline <svg> in templates/b.html.twig:1 (render the icon through the icon component, e.g. ux_icon())\n"
            . "error: SVG markup string in assets/controllers/chart_controller.ts:3 (render the icon server-side in a <template> and clone it)\n",
            $err,
        );
    }

    public function testStaleAllowlistEntriesAreReported(): void
    {
        $root = $this->makeTree([
            'templates/clean.html.twig' => 'no svg any more',
            'assets/sprite.ts' => "export const SPRITE = '<svg><symbol id=\"a\"/></svg>';\n",
        ]);

        [$code, , $err] = $this->runCommand($root, ['--allow=./templates/clean.html.twig', '--allow=templates/gone.html.twig', '--allow=assets/sprite.ts']);

        self::assertSame(1, $code);
        self::assertSame(
            "error: --allow=templates/clean.html.twig no longer contains SVG: remove it from the allowlist\n"
            . "error: --allow=templates/gone.html.twig does not exist or is outside the scanned directories\n",
            $err,
        );
    }

    public function testCustomDirectoriesSuffixesAndNoScripts(): void
    {
        $root = $this->makeTree([
            'resources/views/icon.blade.php' => '<svg></svg>',
            'resources/views/page.html' => 'ok',
            'resources/js/app.js' => "'<circle r=\"1\"/>'",
            'resources/js/app.ts' => "'<rect/>'",
            'templates/ignored.html.twig' => '<svg></svg>',
        ]);

        [$code, , $err] = $this->runCommand($root, ['--templates=resources/views', '--template-ext=blade.php, .html', '--scripts=resources/js', '--script-ext=js']);
        self::assertSame(1, $code);
        self::assertStringContainsString('inline <svg> in resources/views/icon.blade.php:1', $err);
        self::assertStringContainsString('SVG markup string in resources/js/app.js:1', $err);
        self::assertStringNotContainsString('app.ts', $err);
        self::assertStringNotContainsString('ignored', $err);

        [$code, $out] = $this->runCommand($root, ['--templates=resources/views', '--template-ext=.html', '--no-scripts']);
        self::assertSame(0, $code);
        self::assertSame("OK: no inline SVG (1 template(s), 0 script(s), 0 allowed).\n", $out);
    }

    public function testRootOptionAndMissingDefaultDirectories(): void
    {
        $root = $this->makeTree(['README.md' => '']);

        [$code, $out] = $this->runCommand(\dirname($root), ['--root=' . basename($root)]);
        self::assertSame(0, $code);
        self::assertSame("OK: no inline SVG (0 template(s), 0 script(s), 0 allowed).\n", $out);

        [$code, $out] = $this->runCommand('/', ['--root=' . $root . '/']);
        self::assertSame(0, $code, $out);
    }

    public function testHelpAndUsageErrors(): void
    {
        $root = $this->makeTree(['templates/a.twig' => '']);

        [$code, $out] = $this->runCommand($root, ['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: check-no-inline-svg', $out);

        foreach ([
            ['--bogus=1', 'Unknown option --bogus=1'],
            ['--templates', 'Unknown or empty option --templates'],
            ['--allow=', 'Unknown or empty option --allow='],
            ['--template-ext=,', '--template-ext needs at least one suffix'],
            ['--script-ext= ', '--script-ext needs at least one suffix'],
            ['--templates=views', 'directory not found: views'],
            ['--scripts=js', 'directory not found: js'],
            ['--root=missing', 'root directory not found: ' . $root . '/missing'],
        ] as [$arg, $message]) {
            [$code, , $err] = $this->runCommand($root, [$arg]);
            self::assertSame(2, $code, $arg);
            self::assertStringContainsString($message, $err, $arg);
        }

        // An explicit missing script directory is fine when scripts are not checked.
        [$code] = $this->runCommand($root, ['--scripts=js', '--no-scripts']);
        self::assertSame(0, $code);
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
        $code = (new NoInlineSvgCommand($stdout, $stderr, $cwd))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
