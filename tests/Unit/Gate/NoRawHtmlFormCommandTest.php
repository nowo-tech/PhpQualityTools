<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Twig\NoRawHtmlFormCommand;
use NowoTech\PhpQualityTools\Gate\Twig\TemplateFiles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoRawHtmlFormCommand::class)]
#[CoversClass(TemplateFiles::class)]
final class NoRawHtmlFormCommandTest extends TestCase
{
    use TempTreeTrait;

    public function testSymfonyFormMarkupPasses(): void
    {
        $root = $this->makeTree([
            'src/Resources/views/page.html.twig' => "{{ form_start(form) }}\n{{ form_row(form.name) }}\n{{ form_end(form) }}\n<span>format</span> <formula>x</formula>",
            'demo/symfony7/templates/index.html.twig' => '{{ form(form) }}',
        ]);

        [$code, $out, $err] = $this->runCommand($root, []);

        self::assertSame(0, $code, $err);
        self::assertSame("check-no-raw-html-form: OK (src/Resources/views demo/symfony7/templates)\n", $out);
    }

    public function testRawMarkupInViewsAndDemosIsReportedWithFileAndLine(): void
    {
        $root = $this->makeTree([
            'src/Resources/views/a.html.twig' => "<div>\n  <form method=\"post\">\n  <INPUT type=\"text\">\n<textarea>\n<select>\n<input\n",
            'demo/symfony8/nested/templates/b.html.twig' => '<input type="hidden">',
            'demo/symfony8/vendor/templates/skipped.html.twig' => '<input>',
            'demo/symfony8/node_modules/templates/skipped.html.twig' => '<input>',
            'demo/symfony8/other/c.html.twig' => '<input>',
        ]);

        [$code, $out, $err] = $this->runCommand($root, []);

        self::assertSame(1, $code);
        self::assertSame('', $out);
        self::assertSame(
            "ERROR: raw <form> / <input> / <textarea> / <select> in Twig (REQ-TWIG-005):\n"
            . "src/Resources/views/a.html.twig:2:  <form method=\"post\">\n"
            . "src/Resources/views/a.html.twig:3:  <INPUT type=\"text\">\n"
            . "src/Resources/views/a.html.twig:4:<textarea>\n"
            . "src/Resources/views/a.html.twig:5:<select>\n"
            . "src/Resources/views/a.html.twig:6:<input\n"
            . "demo/symfony8/nested/templates/b.html.twig:1:<input type=\"hidden\">\n",
            $err,
        );
    }

    public function testFormThemesAreExemptUnlessStrict(): void
    {
        $root = $this->makeTree([
            'src/Resources/views/form/theme.html.twig' => '<input type="text">',
            'src/Resources/views/Form/widget.html.twig' => '<input type="text">',
            'src/Resources/views/partials/_my_theme_x.html.twig' => '<select></select>',
        ]);

        [$code, $out, $err] = $this->runCommand($root, []);
        self::assertSame(0, $code, $err);
        self::assertStringContainsString('OK', $out);

        [$code, , $err] = $this->runCommand($root, ['--strict']);
        self::assertSame(1, $code);
        self::assertStringContainsString('src/Resources/views/form/theme.html.twig:1:', $err);
        self::assertStringContainsString('src/Resources/views/Form/widget.html.twig:1:', $err);
        self::assertStringContainsString('_my_theme_x.html.twig:1:', $err);
    }

    public function testTheIgnoreMarkerOnTheLineAboveExemptsOneControl(): void
    {
        $root = $this->makeTree([
            'src/Resources/views/confirm.html.twig' => "{# twig-005-ignore: nameless type-to-confirm input #}\n<input type=\"text\">\n<input type=\"text\">\n",
            'src/Resources/views/first.html.twig' => '<input type="text">',
        ]);

        [$code, , $err] = $this->runCommand($root, []);

        self::assertSame(1, $code);
        self::assertStringContainsString('confirm.html.twig:3:', $err);
        self::assertStringNotContainsString('confirm.html.twig:2:', $err);
        self::assertStringContainsString('first.html.twig:1:', $err);
    }

    public function testAllowedFilesAreExemptAndStaleEntriesAreWarnedAbout(): void
    {
        $root = $this->makeTree([
            'src/Resources/views/partials/_legacy.html.twig' => '<form></form>',
            'src/Resources/views/partials/_clean.html.twig' => '{{ form(form) }}',
        ]);

        [$code, $out, $err] = $this->runCommand($root, [
            '--allow=./src/Resources/views/partials/_legacy.html.twig',
            '--allow=src/Resources/views/partials/_clean.html.twig',
            '--allow=src/Resources/views/partials/_gone.html.twig',
        ]);

        self::assertSame(0, $code, $err);
        self::assertStringContainsString('OK', $out);
        self::assertStringContainsString('warning: --allow=src/Resources/views/partials/_clean.html.twig no longer contains raw form markup', $err);
        self::assertStringContainsString('warning: --allow=src/Resources/views/partials/_gone.html.twig does not exist', $err);
    }

    public function testExplicitDirectoriesReplaceTheDefaults(): void
    {
        $root = $this->makeTree([
            'src/Resources/views/a.html.twig' => '<input>',
            'resources/views/b.html.twig' => '<input>',
        ]);

        [$code, , $err] = $this->runCommand($root, ['--dir=./resources/views/']);

        self::assertSame(1, $code);
        self::assertStringContainsString('resources/views/b.html.twig:1:', $err);
        self::assertStringNotContainsString('src/Resources', $err);
    }

    public function testWithoutTwigViewsThereIsNothingToCheck(): void
    {
        $root = $this->makeTree(['README.md' => '']);

        [$code, $out] = $this->runCommand(\dirname($root), ['--root=' . basename($root)]);

        self::assertSame(0, $code);
        self::assertSame("check-no-raw-html-form: no Twig views - OK\n", $out);
    }

    public function testHelpAndUsageErrors(): void
    {
        $root = $this->makeTree(['src/Resources/views/a.html.twig' => '']);

        [$code, $out] = $this->runCommand($root, ['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: check-no-raw-html-form', $out);

        foreach ([
            ['--bogus=1', 'Unknown option --bogus=1'],
            ['--dir', 'Unknown or empty option --dir'],
            ['--dir=missing', 'directory not found: missing'],
            ['--root=missing', 'root directory not found: ' . $root . '/missing'],
        ] as [$arg, $message]) {
            [$code, , $err] = $this->runCommand($root, [$arg]);
            self::assertSame(2, $code, $arg);
            self::assertStringContainsString($message, $err, $arg);
        }
    }

    public function testTemplateFilesSkipsMissingDirectories(): void
    {
        $root = $this->makeTree(['README.md' => '']);

        self::assertSame([], TemplateFiles::in($root, 'templates'));
        self::assertSame([], TemplateFiles::demoTemplateDirectories($root));
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
        $code = (new NoRawHtmlFormCommand($stdout, $stderr, $cwd))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
