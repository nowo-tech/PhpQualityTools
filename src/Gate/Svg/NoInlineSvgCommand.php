<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Svg;

/**
 * `bin/check-no-inline-svg`: fail on inline `<svg>` in templates and on SVG markup strings in front-end scripts.
 *
 * Usage: check-no-inline-svg [--root=<dir>] [--templates=<dir>]... [--scripts=<dir>]...
 *        [--template-ext=<.ext,...>] [--script-ext=<.ext,...>] [--allow=<path>]... [--no-scripts]
 *
 * One icon source (an icon component such as Symfony UX Icons) instead of hand-written SVG: templates
 * must not contain `<svg`, scripts must not build `<svg>` / `<path>` / `<circle>` / `<rect>` / `<polyline>` /
 * `<polygon>` markup. Test files (`*.test.*`, `*.spec.*`), `*.d.ts` and `node_modules/` are skipped.
 * `--allow` keeps a file that genuinely needs inline SVG (e.g. a third-party `<symbol>` sprite); an allowed
 * file that is missing or no longer contains SVG is reported, so the allowlist cannot go stale.
 *
 * Exit codes: 0 OK, 1 violations, 2 usage error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class NoInlineSvgCommand
{
    public const USAGE = <<<'TXT'
        Usage: check-no-inline-svg [--root=<dir>] [--templates=<dir>]... [--scripts=<dir>]...
                                   [--template-ext=<.ext,...>] [--script-ext=<.ext,...>]
                                   [--allow=<path>]... [--no-scripts]

        Fails on inline <svg> in templates and on SVG markup strings in front-end scripts
        (render icons through one icon component, e.g. Symfony UX Icons, instead).
          --root=<dir>          project root; every other path is relative to it (default: current directory)
          --templates=<dir>     template directory, repeatable (default: templates)
          --scripts=<dir>       script directory, repeatable (default: assets)
          --template-ext=<list> template suffixes, comma separated (default: .twig)
          --script-ext=<list>   script suffixes, comma separated (default: .ts)
          --allow=<path>        file that may keep inline SVG (e.g. a vendor <symbol> sprite), repeatable;
                                reported when it is missing or no longer contains SVG
          --no-scripts          check templates only
        Skips node_modules/, *.test.*, *.spec.* and *.d.ts. Default directories that do not exist are skipped.
        Exit codes: 0 OK, 1 violations, 2 usage error.
        TXT;

    private const TEMPLATE_PATTERN = '/<svg[\s>\/]/i';

    private const SCRIPT_PATTERN = '/<(svg|path|circle|rect|polyline|polygon)[\s>\/]/i';

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
        $templates = [];
        $scripts = [];
        $templateExt = ['.twig'];
        $scriptExt = ['.ts'];
        $allow = [];
        $checkScripts = true;
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            [$name, $value] = str_contains($arg, '=') ? explode('=', $arg, 2) : [$arg, null];
            if ('--no-scripts' === $arg) {
                $checkScripts = false;
            } elseif (null === $value || '' === $value) {
                return $this->usageError("Unknown or empty option {$arg}");
            } elseif ('--root' === $name) {
                $root = str_starts_with($value, '/') ? $value : $this->cwd . '/' . $value;
            } elseif ('--templates' === $name) {
                $templates[] = $this->normalize($value);
            } elseif ('--scripts' === $name) {
                $scripts[] = $this->normalize($value);
            } elseif ('--template-ext' === $name || '--script-ext' === $name) {
                $suffixes = $this->suffixes($value);
                if ([] === $suffixes) {
                    return $this->usageError("{$name} needs at least one suffix");
                }
                if ('--template-ext' === $name) {
                    $templateExt = $suffixes;
                } else {
                    $scriptExt = $suffixes;
                }
            } elseif ('--allow' === $name) {
                $allow[] = $this->normalize($value);
            } else {
                return $this->usageError("Unknown option {$arg}");
            }
        }

        $root = rtrim($root, '/');
        if (!is_dir($root)) {
            fwrite($this->stderr, "ERROR: root directory not found: {$root}\n");

            return 2;
        }
        foreach ([...$templates, ...($checkScripts ? $scripts : [])] as $dir) {
            if (!is_dir($root . '/' . $dir)) {
                fwrite($this->stderr, "ERROR: directory not found: {$dir} (under {$root})\n");

                return 2;
            }
        }
        $templates = [] !== $templates ? $templates : $this->existing($root, ['templates']);
        $scripts = $checkScripts ? ([] !== $scripts ? $scripts : $this->existing($root, ['assets'])) : [];

        $errors = [];
        $allowed = array_fill_keys($allow, false);
        $templateCount = 0;
        foreach ($this->files($root, $templates, $templateExt) as $relative => $contents) {
            ++$templateCount;
            $line = $this->firstMatchLine(self::TEMPLATE_PATTERN, $contents);
            if (isset($allowed[$relative])) {
                $allowed[$relative] = null !== $line;
                continue;
            }
            if (null !== $line) {
                $errors[] = "error: inline <svg> in {$relative}:{$line} (render the icon through the icon component, e.g. ux_icon())";
            }
        }
        $scriptCount = 0;
        foreach ($this->files($root, $scripts, $scriptExt) as $relative => $contents) {
            ++$scriptCount;
            $line = $this->firstMatchLine(self::SCRIPT_PATTERN, $contents);
            if (isset($allowed[$relative])) {
                $allowed[$relative] = null !== $line;
                continue;
            }
            if (null !== $line) {
                $errors[] = "error: SVG markup string in {$relative}:{$line} (render the icon server-side in a <template> and clone it)";
            }
        }
        foreach ($allowed as $path => $stillNeeded) {
            if (!$stillNeeded) {
                $errors[] = "error: --allow={$path} " . (is_file($root . '/' . $path) ? 'no longer contains SVG: remove it from the allowlist' : 'does not exist or is outside the scanned directories');
            }
        }

        if ([] !== $errors) {
            fwrite($this->stderr, implode("\n", $errors) . "\n");

            return 1;
        }
        fwrite($this->stdout, \sprintf(
            "OK: no inline SVG (%d template(s), %d script(s), %d allowed).\n",
            $templateCount,
            $scriptCount,
            \count($allowed),
        ));

        return 0;
    }

    /**
     * @param list<string> $dirs     directories relative to $root
     * @param list<string> $suffixes
     *
     * @return array<string, string> path relative to $root => contents, sorted by path
     */
    private function files(string $root, array $dirs, array $suffixes): array
    {
        $files = [];
        foreach ($dirs as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                $relative = substr($file->getPathname(), \strlen($root) + 1);
                if (!$file->isFile() || str_contains('/' . $relative, '/node_modules/') || !$this->matches($file->getFilename(), $suffixes)) {
                    continue;
                }
                $files[$relative] = (string) file_get_contents($file->getPathname());
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * @param list<string> $suffixes
     */
    private function matches(string $name, array $suffixes): bool
    {
        if (str_ends_with($name, '.d.ts') || 1 === preg_match('/\.(test|spec)\.[^.]+$/', $name)) {
            return false;
        }
        foreach ($suffixes as $suffix) {
            if (str_ends_with($name, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function firstMatchLine(string $pattern, string $contents): ?int
    {
        if (1 !== preg_match($pattern, $contents, $match, \PREG_OFFSET_CAPTURE)) {
            return null;
        }

        return substr_count($contents, "\n", 0, $match[0][1]) + 1;
    }

    /**
     * @param list<string> $dirs
     *
     * @return list<string>
     */
    private function existing(string $root, array $dirs): array
    {
        return array_values(array_filter($dirs, static fn (string $dir): bool => is_dir($root . '/' . $dir)));
    }

    /**
     * @return list<string>
     */
    private function suffixes(string $value): array
    {
        $suffixes = [];
        foreach (explode(',', $value) as $suffix) {
            $suffix = trim($suffix);
            if ('' !== $suffix) {
                $suffixes[] = str_starts_with($suffix, '.') ? $suffix : '.' . $suffix;
            }
        }

        return $suffixes;
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
