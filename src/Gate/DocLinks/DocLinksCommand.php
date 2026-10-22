<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\DocLinks;

use NowoTech\PhpQualityTools\Process\CommandRunnerInterface;
use NowoTech\PhpQualityTools\Process\ExecCommandRunner;

/**
 * `bin/check-doc-links`: fail on broken relative links and images in the repository Markdown.
 *
 * Usage: check-doc-links [--root=<dir>] [--exclude=<pattern>]... [--check-anchors] [--no-git]
 *
 * Files: the tracked and new, not ignored `*.md` files (`git ls-files --cached --others --exclude-standard`),
 * or, outside a git work tree (or with `--no-git`), every `*.md` under the root except `.git/`, `vendor/`,
 * `node_modules/` and `var/`. In each file, every `[text](target)` / `![alt](target)` with a relative target
 * must point to an existing file or directory; `/target` is relative to the root (GitHub semantics). URLs,
 * `mailto:`, fenced code blocks, inline code and targets outside the root are skipped. With
 * `--check-anchors`, `#fragment`s pointing into Markdown files (and in-page `#fragment`s) must match a
 * heading slug or an HTML anchor of the target (see MarkdownAnchors).
 *
 * Exit codes: 0 OK, 1 broken links, 2 usage error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class DocLinksCommand
{
    public const USAGE = <<<'TXT'
        Usage: check-doc-links [--root=<dir>] [--exclude=<pattern>]... [--check-anchors] [--no-git]

        Fails on broken relative links and images in Markdown files.
          --root=<dir>         repository root (default: current directory)
          --exclude=<pattern>  skip Markdown files: path, directory prefix or fnmatch glob relative to the root;
                               repeatable
          --check-anchors      also check #fragments against GitHub heading slugs and HTML anchors
          --no-git             walk the directory tree instead of `git ls-files` (skips .git, vendor,
                               node_modules, var)
        Files: tracked + new, not ignored *.md (git), or every *.md outside a git work tree.
        Skips URLs, mailto:, fenced code, inline code and targets outside the root.
        Exit codes: 0 OK, 1 broken links, 2 usage error.
        TXT;

    /** Zero-width at every `[`, so a badge image inside a link (`[![alt](img)](url)`) yields both targets. */
    private const LINK = '/(?=\[(?:[^\[\]]|\[[^\]]*\])*\]\(\s*<?([^)\s>]+)>?(?:\s+"[^"]*")?\s*\))/';

    private const SKIPPED_DIRS = ['.git', 'vendor', 'node_modules', 'var'];

    /** @var array<string, array<string, true>> */
    private array $anchors = [];

    private readonly CommandRunnerInterface $runner;

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private readonly string $cwd,
        ?CommandRunnerInterface $runner = null,
    ) {
        $this->runner = $runner ?? new ExecCommandRunner();
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $root = $this->cwd;
        $exclude = [];
        $checkAnchors = false;
        $useGit = true;
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            if ('--check-anchors' === $arg) {
                $checkAnchors = true;
            } elseif ('--no-git' === $arg) {
                $useGit = false;
            } elseif (str_starts_with($arg, '--root=') && '' !== substr($arg, 7)) {
                $root = str_starts_with(substr($arg, 7), '/') ? substr($arg, 7) : $this->cwd . '/' . substr($arg, 7);
            } elseif (str_starts_with($arg, '--exclude=') && '' !== substr($arg, 10)) {
                $exclude[] = trim((string) preg_replace('#^(\./)+#', '', substr($arg, 10)), '/');
            } else {
                fwrite($this->stderr, "ERROR: Unknown or empty option {$arg}\n" . self::USAGE . "\n");

                return 2;
            }
        }
        $root = rtrim((string) realpath($root), '/');
        if ('' === $root || !is_dir($root)) {
            fwrite($this->stderr, "ERROR: root directory not found\n");

            return 2;
        }

        $files = array_values(array_filter(
            ($useGit ? $this->gitFiles($root) : null) ?? $this->walk($root, ''),
            static fn (string $file): bool => !self::excluded($file, $exclude) && is_file($root . '/' . $file),
        ));
        sort($files);
        // @igor-ignore - CLI gate, per-run cache reset at the start of run(); never a worker service
        $this->anchors = [];

        $broken = [];
        foreach ($files as $file) {
            foreach ($this->check($root, $file, $checkAnchors) as $problem) {
                $broken[] = $problem;
            }
        }

        if ([] !== $broken) {
            fwrite($this->stderr, \sprintf("Broken relative links / images%s (%d):\n%s\n", $checkAnchors ? ' / anchors' : '', \count($broken), implode("\n", $broken)));

            return 1;
        }
        fwrite($this->stdout, \sprintf(
            "OK: doc links (%d Markdown files, no broken relative link%s).\n",
            \count($files),
            $checkAnchors ? ', image or anchor' : ' or image',
        ));

        return 0;
    }

    /**
     * @return list<string> problems as `file:line → target`
     */
    private function check(string $root, string $file, bool $checkAnchors): array
    {
        $problems = [];
        $inFence = false;
        $contents = (string) file_get_contents($root . '/' . $file);
        foreach (preg_split('/\r\n|\n|\r/', $contents) ?: [] as $index => $line) {
            if (1 === preg_match('/^\s*(```|~~~)/', $line)) {
                $inFence = !$inFence;
                continue;
            }
            if ($inFence) {
                continue;
            }
            // Inline code spans may show link syntax as an example.
            $text = (string) preg_replace('/`[^`]*`/', '', $line);
            preg_match_all(self::LINK, $text, $matches);
            foreach ($matches[1] as $rawTarget) {
                $problem = $this->checkTarget($root, $file, $rawTarget, $checkAnchors);
                if (null !== $problem) {
                    $problems[] = \sprintf('%s:%d → %s%s', $file, $index + 1, $rawTarget, $problem);
                }
            }
        }

        return $problems;
    }

    /**
     * @return string|null null when the target is fine or skipped, else a suffix for the report ('' or ' (…)')
     */
    private function checkTarget(string $root, string $file, string $rawTarget, bool $checkAnchors): ?string
    {
        if (1 === preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $rawTarget)) {
            return null;
        }
        $hash = strpos($rawTarget, '#');
        $pathPart = false === $hash ? $rawTarget : substr($rawTarget, 0, $hash);
        $fragment = false === $hash ? '' : rawurldecode(substr($rawTarget, $hash + 1));
        $pathPart = rawurldecode(explode('?', $pathPart, 2)[0]);

        if ('' === $pathPart) {
            // In-page anchor (#section).
            return $checkAnchors && '' !== $fragment && !$this->hasAnchor($root, $file, $fragment) ? ' (no such anchor)' : null;
        }
        $target = $this->normalize(str_starts_with($pathPart, '/') ? $pathPart : \dirname('/' . $file) . '/' . $pathPart);
        if (null === $target) {
            // Outside the repository (workspace-only link).
            return null;
        }
        if (!file_exists($root . $target)) {
            return '';
        }
        if ($checkAnchors && '' !== $fragment && is_file($root . $target) && 1 === preg_match('/\.(md|markdown)$/i', $target)
            && !$this->hasAnchor($root, ltrim($target, '/'), $fragment)) {
            return ' (no such anchor)';
        }

        return null;
    }

    private function hasAnchor(string $root, string $file, string $fragment): bool
    {
        // @igor-ignore - CLI gate, per-run cache reset at the start of run(); never a worker service
        $this->anchors[$file] ??= MarkdownAnchors::fromMarkdown((string) file_get_contents($root . '/' . $file));
        $lower = \function_exists('mb_strtolower') ? mb_strtolower($fragment, 'UTF-8') : strtolower($fragment);

        return isset($this->anchors[$file][$lower]);
    }

    /**
     * Resolves `.` and `..` in an absolute, root-relative path; null when it climbs above the root.
     */
    private function normalize(string $path): ?string
    {
        $parts = [];
        foreach (explode('/', $path) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                if ([] === $parts) {
                    return null;
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }

        return '/' . implode('/', $parts);
    }

    /**
     * @return list<string>|null Markdown files relative to the root, or null when git cannot list them
     */
    private function gitFiles(string $root): ?array
    {
        // Subshell: git's own stderr (not a work tree, git missing) is discarded, not mixed into the file list.
        $result = $this->runner->run('(git -C ' . escapeshellarg($root) . " ls-files -z --cached --others --exclude-standard -- '*.md' 2>/dev/null)");
        if (0 !== $result['exitCode']) {
            return null;
        }

        // Paths are relative to -C (the root), also when the root is a subdirectory of the work tree.
        return array_values(array_filter(explode("\0", implode("\n", $result['output'])), static fn (string $file): bool => '' !== $file));
    }

    /**
     * @return list<string>
     */
    private function walk(string $root, string $relative): array
    {
        $files = [];
        foreach (scandir($root . '/' . $relative) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = '' === $relative ? $entry : $relative . '/' . $entry;
            if (is_dir($root . '/' . $path)) {
                if (!\in_array($entry, self::SKIPPED_DIRS, true) && !is_link($root . '/' . $path)) {
                    array_push($files, ...$this->walk($root, $path));
                }
            } elseif (str_ends_with($entry, '.md')) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * @param list<string> $patterns
     */
    private static function excluded(string $file, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (false !== strpbrk($pattern, '*?[') ? fnmatch($pattern, $file) : ($file === $pattern || str_starts_with($file, $pattern . '/'))) {
                return true;
            }
        }

        return false;
    }
}
