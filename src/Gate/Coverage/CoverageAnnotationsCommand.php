<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage;

/**
 * `bin/check-coverage-annotations`: fail on coverage annotations and `match` placements the coverage driver silently drops.
 *
 * Usage: check-coverage-annotations [--root=<dir>] [--paths=<dir>]... [--exclude=<pattern>]... [--no-match]
 *
 * 1. php-code-coverage only honours the line annotations (codeCoverageIgnore, codeCoverageIgnoreStart and
 *    codeCoverageIgnoreEnd after "// @") when the whole `//` comment is exactly that (it compares the trimmed comment
 *    token): trailing text, `#` / `/* … *\/` comments or a double space are ignored without a warning, so the lines
 *    stay uncovered and the coverage gate fails late. Any non-doc comment that mentions the annotation in another form
 *    is reported. Docblock annotations are not line annotations and are skipped. (This docblock spells the annotation
 *    without its "@" on purpose: in a class docblock it would exclude the whole class from coverage.)
 * 2. A `match (…)` that is the operand of `?`, `?:`, `??` or follows `:` (ternary else branch, named argument)
 *    cannot be mapped to lines by the coverage driver; assign it to a variable first. `--no-match` skips this check.
 *
 * Exit codes: 0 OK, 1 violations, 2 usage error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CoverageAnnotationsCommand
{
    public const USAGE = <<<'TXT'
        Usage: check-coverage-annotations [--root=<dir>] [--paths=<dir>]... [--exclude=<pattern>]... [--no-match]

        Fails on coverage annotations php-code-coverage silently ignores and on match() placements it cannot map.
          --root=<dir>        project root; every other path is relative to it (default: current directory)
          --paths=<dir>       directory or PHP file to scan, repeatable (default: src)
          --exclude=<pattern> path, directory prefix or fnmatch glob to skip, repeatable
          --no-match          do not report match() after ?, ?:, ?? or :
        Only "// @codeCoverageIgnore", "// @codeCoverageIgnoreStart" and "// @codeCoverageIgnoreEnd" (nothing else
        in the comment) are honoured; put the reason in a comment on the line above.
        Skips vendor/ and node_modules/. A missing default directory is skipped.
        Exit codes: 0 OK, 1 violations, 2 usage error.
        TXT;

    private const VALID_ANNOTATIONS = [
        '// @codeCoverageIgnore', '//@codeCoverageIgnore',
        '// @codeCoverageIgnoreStart', '//@codeCoverageIgnoreStart',
        '// @codeCoverageIgnoreEnd', '//@codeCoverageIgnoreEnd',
    ];

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private readonly string $cwd,
    ) {
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $root = $this->cwd;
        $paths = [];
        $exclude = [];
        $checkMatch = true;
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            [$name, $value] = str_contains($arg, '=') ? explode('=', $arg, 2) : [$arg, null];
            if ('--no-match' === $arg) {
                $checkMatch = false;
            } elseif (null === $value || '' === $value) {
                return $this->usageError("Unknown or empty option {$arg}");
            } elseif ('--root' === $name) {
                $root = str_starts_with($value, '/') ? $value : $this->cwd . '/' . $value;
            } elseif ('--paths' === $name) {
                $paths[] = $this->normalize($value);
            } elseif ('--exclude' === $name) {
                $exclude[] = $this->normalize($value);
            } else {
                return $this->usageError("Unknown option {$arg}");
            }
        }

        $root = rtrim($root, '/');
        if (!is_dir($root)) {
            fwrite($this->stderr, "ERROR: root directory not found: {$root}\n");

            return 2;
        }
        foreach ($paths as $path) {
            if (!file_exists($root . '/' . $path)) {
                fwrite($this->stderr, "ERROR: path not found: {$path} (under {$root})\n");

                return 2;
            }
        }
        if ([] === $paths) {
            $paths = is_dir($root . '/src') ? ['src'] : [];
        }

        $errors = [];
        $files = $this->phpFiles($root, $paths, $exclude);
        foreach ($files as $relative) {
            foreach ($this->check((string) file_get_contents($root . '/' . $relative), $checkMatch) as [$line, $message]) {
                $errors[] = "error: {$relative}:{$line} {$message}";
            }
        }

        if ([] !== $errors) {
            fwrite($this->stderr, implode("\n", $errors) . "\n");
            fwrite($this->stderr, \sprintf("Coverage annotations: %d violation(s).\n", \count($errors)));

            return 1;
        }
        fwrite($this->stdout, \sprintf("OK: coverage annotations (%d PHP file(s)).\n", \count($files)));

        return 0;
    }

    /**
     * @return list<array{int, string}> line => message, in source order
     */
    private function check(string $source, bool $checkMatch): array
    {
        $tokens = token_get_all($source);
        $findings = [];
        $previous = null;
        foreach ($tokens as $index => $token) {
            if (\is_array($token) && \T_COMMENT === $token[0] && str_contains($token[1], '@codeCoverageIgnore')) {
                $comment = trim($token[1]);
                if (!\in_array($comment, self::VALID_ANNOTATIONS, true)) {
                    $findings[] = [$token[2], \sprintf(
                        '"%s" is ignored by php-code-coverage (write exactly "// @codeCoverageIgnore[Start|End]", the reason on the line above)',
                        $this->shorten($comment),
                    )];
                }
            }
            if ($checkMatch && \is_array($token) && \T_MATCH === $token[0] && \in_array($previous, ['?', ':', '??'], true) && '(' === $this->nextSignificant($tokens, $index)) {
                $findings[] = [$token[2], \sprintf('match() after "%s" cannot be mapped by the coverage driver (assign the match to a variable first)', $previous)];
            }
            if (!\is_array($token) || !\in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)) {
                $previous = \is_array($token) ? $token[1] : $token;
            }
        }

        return $findings;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function nextSignificant(array $tokens, int $index): ?string
    {
        $count = \count($tokens);
        for ($i = $index + 1; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (\is_array($token) && \in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)) {
                continue;
            }

            return \is_array($token) ? $token[1] : $token;
        }

        return null;
    }

    private function shorten(string $comment): string
    {
        $comment = (string) preg_replace('/\s*\R\s*/', ' ', $comment);

        return mb_strlen($comment) > 80 ? mb_substr($comment, 0, 77) . '...' : $comment;
    }

    /**
     * @param list<string> $paths
     * @param list<string> $exclude
     *
     * @return list<string> sorted relative paths
     */
    private function phpFiles(string $root, array $paths, array $exclude): array
    {
        $files = [];
        foreach ($paths as $path) {
            $absolute = $root . '/' . $path;
            if (is_file($absolute)) {
                $files[$path] = true;
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS));
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                $relative = substr($file->getPathname(), \strlen($root) + 1);
                if ($file->isFile() && 'php' === $file->getExtension() && !preg_match('#(^|/)(vendor|node_modules)/#', $relative)) {
                    $files[$relative] = true;
                }
            }
        }
        $result = [];
        foreach (array_keys($files) as $file) {
            $file = (string) $file;
            if (!$this->excluded($file, $exclude)) {
                $result[] = $file;
            }
        }
        sort($result);

        return $result;
    }

    /**
     * @param list<string> $patterns
     */
    private function excluded(string $file, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (false !== strpbrk($pattern, '*?[') ? fnmatch($pattern, $file) : ($file === $pattern || str_starts_with($file, $pattern . '/'))) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $path): string
    {
        return trim((string) preg_replace('#^(\./)+#', '', $path), '/');
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "ERROR: {$message}\n" . self::USAGE . "\n");

        return 2;
    }
}
