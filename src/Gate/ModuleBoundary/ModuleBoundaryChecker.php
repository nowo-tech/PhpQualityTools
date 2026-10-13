<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

use function fnmatch;

/**
 * Runs a {@see ModuleBoundaryConfig} against a project tree (paths are relative to `$root`).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ModuleBoundaryChecker
{
    private string $root = '';

    /** @var array<string, string> */
    private array $contents = [];

    /** @var array<string, list<array{name: string, line: int}>> */
    private array $imports = [];

    public function __construct(
        private readonly ImportExtractor $extractor = new ImportExtractor(),
    ) {
    }

    /**
     * Path pattern semantics: a pattern with `*`, `?` or `[` is an fnmatch glob (`*` also matches `/`);
     * otherwise it matches the exact path or anything below it as a directory.
     */
    public static function pathMatches(string $file, string $pattern): bool
    {
        if (false !== strpbrk($pattern, '*?[')) {
            return \fnmatch($pattern, $file);
        }
        $pattern = rtrim($pattern, '/');
        if ('' === $pattern) {
            return true;
        }

        return $file === $pattern || str_starts_with($file, $pattern . '/');
    }

    /**
     * @return array{violations: list<Violation>, files: int}
     */
    public function check(ModuleBoundaryConfig $config, string $root): array
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
        $this->contents = [];
        $this->imports = [];

        $files = $this->phpFiles($config->paths, [], $config->ignore);
        $violations = $this->checkEdges($config, $files);
        foreach ($config->rules as $rule) {
            $violation = $this->checkRule($config, $rule);
            if ($violation instanceof Violation) {
                $violations[] = $violation;
            }
        }

        return ['violations' => $violations, 'files' => \count($files)];
    }

    /**
     * @param list<string> $files
     *
     * @return list<Violation>
     */
    private function checkEdges(ModuleBoundaryConfig $config, array $files): array
    {
        if ([] === $config->allowedEdges && [] === $config->forbiddenEdges) {
            return [];
        }

        $violations = [];
        foreach ($files as $file) {
            $from = $this->moduleOfPath($config, $file);
            if (null === $from) {
                continue;
            }
            foreach ($this->importsOf($file, $config->includeFullyQualified) as $import) {
                $to = $this->moduleOfName($config, $import['name']);
                if (null === $to || $to === $from) {
                    continue;
                }
                $where = \sprintf('%s:%d imports %s', $file, $import['line'], $import['name']);
                if (isset($config->allowedEdges[$from]) && !\in_array($to, $config->allowedEdges[$from], true)) {
                    $violations[] = new Violation(\sprintf('%s (%s -> %s is not an allowed edge).', $where, $from, $to));
                }
                foreach ($config->forbiddenEdges as $edge) {
                    if (!\in_array($from, $edge->from, true) || !\in_array($to, $edge->to, true) || $this->matchesAny($file, $edge->except)) {
                        continue;
                    }
                    $violations[] = new Violation(
                        \sprintf('%s (%s -> %s is forbidden)', $where, $from, $to) . ('' !== $edge->message ? ': ' . $edge->message : '.'),
                    );
                }
            }
        }

        return $violations;
    }

    private function checkRule(ModuleBoundaryConfig $config, Rule $rule): ?Violation
    {
        $details = [];
        switch ($rule->type) {
            case 'forbid_path':
                foreach ($rule->paths as $path) {
                    if (file_exists($this->absolute($path))) {
                        $details[] = rtrim($path, '/');
                    }
                }

                return [] === $details ? null : new Violation($rule->message ?: 'Forbidden path(s) exist:', $details);

            case 'require_path':
                foreach ($rule->paths as $path) {
                    if (!file_exists($this->absolute($path))) {
                        $details[] = rtrim($path, '/');
                    }
                }

                return [] === $details ? null : new Violation($rule->message ?: 'Required path(s) missing:', $details);

            case 'forbid_import':
                foreach ($this->phpFiles($rule->in ?: $config->paths, $rule->except, $config->ignore) as $file) {
                    foreach ($this->importsOf($file, $config->includeFullyQualified) as $import) {
                        if ($this->importMatches($import['name'], $rule->imports)) {
                            $details[] = \sprintf('%s:%d imports %s', $file, $import['line'], $import['name']);
                        }
                    }
                }

                return [] === $details ? null : new Violation($rule->message ?: 'Forbidden import(s):', $details);

            case 'forbid_pattern':
                foreach ($this->phpFiles($rule->in ?: $config->paths, $rule->except, $config->ignore) as $file) {
                    $content = $this->contentOf($file);
                    if (1 === preg_match((string) $rule->pattern, $content, $match, \PREG_OFFSET_CAPTURE)) {
                        $details[] = \sprintf('%s:%d', $file, 1 + substr_count(substr($content, 0, $match[0][1]), "\n"));
                    }
                }

                return [] === $details ? null : new Violation($rule->message ?: \sprintf('Forbidden pattern %s found in:', $rule->pattern), $details);

            case 'require_pattern':
                foreach ($this->phpFiles($rule->paths, [], [], false) as $file) {
                    if (1 !== preg_match((string) $rule->pattern, $this->contentOf($file))) {
                        $details[] = $file;
                    }
                }

                return [] === $details ? null : new Violation($rule->message ?: \sprintf('Required pattern %s not found in:', $rule->pattern), $details);

            default: // require_near
                foreach ($this->phpFiles($rule->paths, [], [], false) as $file) {
                    $lines = explode("\n", $this->contentOf($file));
                    foreach ($lines as $index => $line) {
                        if (1 !== preg_match((string) $rule->pattern, $line)) {
                            continue;
                        }
                        $window = implode("\n", \array_slice($lines, $index, $rule->withinLines + 1));
                        if (1 !== preg_match((string) $rule->requires, $window)) {
                            $details[] = \sprintf('%s:%d', $file, $index + 1);
                        }
                    }
                }

                return [] === $details ? null : new Violation(
                    $rule->message ?: \sprintf('%s without %s within %d line(s):', $rule->pattern, $rule->requires, $rule->withinLines),
                    $details,
                );
        }
    }

    /**
     * @param list<string> $imports
     */
    private function importMatches(string $name, array $imports): bool
    {
        $name = strtolower($name);
        foreach ($imports as $import) {
            $import = strtolower($import);
            if ($name === $import || (str_ends_with($import, '\\') && str_starts_with($name, $import))) {
                return true;
            }
        }

        return false;
    }

    private function moduleOfPath(ModuleBoundaryConfig $config, string $file): ?string
    {
        $best = null;
        $bestLength = -1;
        foreach ($config->modules as $name => $module) {
            if (null === $module['path']) {
                continue;
            }
            $path = rtrim($module['path'], '/');
            if (self::pathMatches($file, $path) && \strlen($path) > $bestLength) {
                $best = $name;
                $bestLength = \strlen($path);
            }
        }

        return $best;
    }

    private function moduleOfName(ModuleBoundaryConfig $config, string $name): ?string
    {
        $name = strtolower($name);
        $best = null;
        $bestLength = -1;
        foreach ($config->modules as $module => $definition) {
            $namespace = strtolower($definition['namespace']);
            if (($name === $namespace || str_starts_with($name, $namespace . '\\')) && \strlen($namespace) > $bestLength) {
                $best = $module;
                $bestLength = \strlen($namespace);
            }
        }

        return $best;
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesAny(string $file, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::pathMatches($file, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Existing PHP files (relative paths, sorted) under the given files / directories.
     * Explicitly listed files are kept whatever their extension; `$skipIgnored` applies the global `ignore`.
     *
     * @param list<string> $roots
     * @param list<string> $except
     * @param list<string> $ignore
     *
     * @return list<string>
     */
    private function phpFiles(array $roots, array $except, array $ignore, bool $skipIgnored = true): array
    {
        $files = [];
        foreach ($roots as $root) {
            $root = rtrim($root, '/');
            $absolute = $this->absolute($root);
            if (is_file($absolute)) {
                $files[$root] = true;
                continue;
            }
            if (!is_dir($absolute)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS));
            /** @var \SplFileInfo $info */
            foreach ($iterator as $info) {
                if ($info->isFile() && 'php' === $info->getExtension()) {
                    $relative = ('' === $root ? '' : $root . '/') . str_replace('\\', '/', substr($info->getPathname(), \strlen($absolute) + 1));
                    $files[$relative] = true;
                }
            }
        }

        $result = [];
        foreach (array_keys($files) as $file) {
            $file = (string) $file;
            if ($this->matchesAny($file, $except) || ($skipIgnored && $this->matchesAny($file, $ignore))) {
                continue;
            }
            $result[] = $file;
        }
        sort($result);

        return $result;
    }

    private function absolute(string $path): string
    {
        $path = rtrim($path, '/');

        return '' === $path || '.' === $path ? $this->root : $this->root . '/' . $path;
    }

    private function contentOf(string $file): string
    {
        return $this->contents[$file] ??= (string) file_get_contents($this->absolute($file));
    }

    /**
     * @return list<array{name: string, line: int}>
     */
    private function importsOf(string $file, bool $includeFullyQualified): array
    {
        return $this->imports[$file] ??= $this->extractor->extract($this->contentOf($file), $includeFullyQualified);
    }
}
