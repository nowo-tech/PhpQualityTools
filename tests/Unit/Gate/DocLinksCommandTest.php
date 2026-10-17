<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\DocLinks\DocLinksCommand;
use NowoTech\PhpQualityTools\Gate\DocLinks\MarkdownAnchors;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class DocLinksCommandTest extends TestCase
{
    use TempTreeTrait;

    private const DOCS = [
        'README.md' => <<<'MD'
            # Project

            [![CI](docs/images/badge.svg)](docs/USAGE.md) [Usage](./docs/USAGE.md#gate-scripts) [Web](https://example.com/x.md)
            [Mail](mailto:a@example.com) [Top](#project) [Root](/docs/USAGE.md "Title") [Dir](docs/) [Workspace](../../OTHER.md)
            [Encoded](docs/My%20File.md) [Query](docs/USAGE.md?plain=1) [Code](src/app.php#L10) <docs/not-a-link.md>
            `[inline](missing-inline.md)`

            ```markdown
            [fenced](missing-fenced.md)
            ```
            MD,
        'docs/USAGE.md' => "# Usage\n\n## Gate scripts\n\n[Back](../README.md#project) [Self](#usage) [Angle](<../README.md>)\n",
        'docs/My File.md' => "Spaces\n",
        'docs/images/badge.svg' => '<svg/>',
        'src/app.php' => '<?php',
    ];

    public function testValidDocsPassInAndOutsideGit(): void
    {
        $root = $this->makeTree(self::DOCS);

        [$code, $out, $err] = $this->runCommand($root, ['--check-anchors']);
        self::assertSame(0, $code, $err);
        self::assertSame("OK: doc links (3 Markdown files, no broken relative link, image or anchor).\n", $out);

        $this->git($root, ['init', '--quiet']);
        file_put_contents($root . '/.gitignore', "ignored/\n");
        $this->makeFile($root, 'ignored/broken.md', '[x](nowhere.md)');
        $this->makeFile($root, 'vendor/pkg/README.md', '[x](nowhere.md)');
        file_put_contents($root . '/.gitignore', "ignored/\nvendor/\n");

        [$code, $out] = $this->runCommand($root, []);
        self::assertSame(0, $code, $out);
        self::assertSame("OK: doc links (3 Markdown files, no broken relative link or image).\n", $out);

        // Without git the ignored file is scanned (vendor/ is always skipped by the walk).
        [$code, , $err] = $this->runCommand($root, ['--no-git']);
        self::assertSame(1, $code);
        self::assertSame("Broken relative links / images (1):\nignored/broken.md:1 → nowhere.md\n", $err);

        [$code] = $this->runCommand($root, ['--no-git', '--exclude=ignored']);
        self::assertSame(0, $code);
    }

    public function testReportsBrokenLinksImagesAndAnchors(): void
    {
        $root = $this->makeTree([
            ...self::DOCS,
            'docs/guide.md' => "# Guide\n\n![Shot](images/missing.webp) [Gone](../GONE.md)\n[Bad anchor](USAGE.md#nope) [In page](#missing)\n[Dir anchor](images/#x) [Non-md](../src/app.php#nope) [Empty](USAGE.md#)\n",
        ]);

        [$code, $out, $err] = $this->runCommand($root, []);
        self::assertSame(1, $code);
        self::assertSame('', $out);
        self::assertSame("Broken relative links / images (2):\ndocs/guide.md:3 → images/missing.webp\ndocs/guide.md:3 → ../GONE.md\n", $err);

        [$code, , $err] = $this->runCommand($root, ['--check-anchors']);
        self::assertSame(1, $code);
        self::assertSame(
            "Broken relative links / images / anchors (4):\n"
            . "docs/guide.md:3 → images/missing.webp\n"
            . "docs/guide.md:3 → ../GONE.md\n"
            . "docs/guide.md:4 → USAGE.md#nope (no such anchor)\n"
            . "docs/guide.md:4 → #missing (no such anchor)\n",
            $err,
        );
    }

    public function testExcludePatterns(): void
    {
        $root = $this->makeTree([
            'docs/a.md' => '[x](missing.md)',
            'specs/001/spec.md' => '[x](missing.md)',
            'CHANGELOG.md' => '[x](missing.md)',
        ]);

        [$code, $out] = $this->runCommand($root, ['--exclude=./docs/', '--exclude=specs/*/spec.md', '--exclude=CHANGELOG.md']);
        self::assertSame(0, $code);
        self::assertSame("OK: doc links (0 Markdown files, no broken relative link or image).\n", $out);
    }

    public function testRootOptionHelpAndUsageErrors(): void
    {
        $root = $this->makeTree(['a.md' => '[ok](a.md)']);

        [$code] = $this->runCommand(\dirname($root), ['--root=' . basename($root)]);
        self::assertSame(0, $code);
        [$code] = $this->runCommand('/', ['--root=' . $root]);
        self::assertSame(0, $code);

        [$code, $out] = $this->runCommand($root, ['--help']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: check-doc-links', $out);

        foreach (['--root=', '--exclude=', '--anchors', 'docs'] as $arg) {
            [$code, , $err] = $this->runCommand($root, [$arg]);
            self::assertSame(2, $code, $arg);
            self::assertStringContainsString("ERROR: Unknown or empty option {$arg}", $err);
        }
        [$code, , $err] = $this->runCommand($root, ['--root=missing']);
        self::assertSame(2, $code);
        self::assertSame("ERROR: root directory not found\n", $err);
    }

    public function testAnchors(): void
    {
        $markdown = <<<'MD'
            # Release v1.2.0 — `make release-check` & [docs](x.md)!
            ## Release v1.2.0 — `make release-check` & [docs](x.md)!
            ## Release v1.2.0 — make release-check & docs ##
            ### Ünïcode Title_with_underscore
            #### Appearance layout {#appearance-layout}
            #
            #NotAHeading
            Setext *title*
            ==============
            Second setext
            ---
            - list item
            ---
            <a id="REQ-MAKE-001"></a> <div name="ignored-name" id='box'></div>

            ```
            ## Fenced heading
            <a id="fenced"></a>
            ```
            Not setext after fence
            ---
            MD;

        self::assertSame([
            'release-v120--make-release-check--docs' => true,
            'release-v120--make-release-check--docs-1' => true,
            'release-v120--make-release-check--docs-2' => true,
            'ünïcode-title_with_underscore' => true,
            'appearance-layout' => true,
            '' => true,
            'setext-title' => true,
            'second-setext' => true,
            'req-make-001' => true,
            'ignored-name' => true,
            'box' => true,
            'not-setext-after-fence' => true,
        ], MarkdownAnchors::fromMarkdown($markdown));

        self::assertSame('a-b--c', MarkdownAnchors::slug('A <b>b</b> &amp; c'));
        self::assertSame('a--b', MarkdownAnchors::slug('A & B'));
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
        $code = (new DocLinksCommand($stdout, $stderr, $cwd))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }

    private function makeFile(string $root, string $path, string $content): void
    {
        if (!is_dir(\dirname($root . '/' . $path))) {
            mkdir(\dirname($root . '/' . $path), 0o777, true);
        }
        file_put_contents($root . '/' . $path, $content);
    }

    /**
     * @param list<string> $args
     */
    private function git(string $cwd, array $args): void
    {
        (new Process(['git', ...$args], $cwd, null, null, 30.0))->mustRun();
    }
}
