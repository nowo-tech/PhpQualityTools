<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * Runs the `layers` section of a {@see ModuleBoundaryConfig} against a project tree.
 *
 * Only the files of the declared contexts are checked (global `ignore` applies). A file's layer comes from its path
 * (`<module path>/<layer path>`, longest match); a referenced name's module and layer from its namespace
 * (`<module namespace>\<layer namespace>`, longest match), for every module: a not yet declared context uses the
 * global layer definitions.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class LayerChecker
{
    public function __construct(
        private ReferenceExtractor $extractor = new ReferenceExtractor(),
    ) {
    }

    /**
     * @return array{violations: list<LayerViolation>, files: int}
     */
    public function check(ModuleBoundaryConfig $config, string $root): array
    {
        $layers = $config->layers;
        if (!$layers instanceof LayerConfig) {
            return ['violations' => [], 'files' => 0];
        }
        $root = rtrim(str_replace('\\', '/', $root), '/');

        $violations = [];
        $files = 0;
        foreach ($layers->contexts as $context => $definition) {
            $modulePath = rtrim((string) $config->modules[$context]['path'], '/');
            foreach ($this->phpFiles($root, $modulePath, $config->ignore) as $file) {
                if ($this->moduleOfPath($config, $file) !== $context) {
                    continue; // a nested module of its own
                }
                ++$files;
                $layer = $this->layerOfPath($file, $modulePath, $definition['layers']);
                $rules = array_values(array_filter($layers->rules, static fn (LayerRule $rule): bool => $rule->appliesTo($context, $layer)));
                if ([] === $rules) {
                    continue;
                }
                $references = $this->extractor->extract((string) file_get_contents($root . '/' . $file));
                foreach ($rules as $rule) {
                    $make = static fn (int $line, string $subject): LayerViolation => new LayerViolation(
                        $context,
                        $layer,
                        $definition['mode'],
                        $rule->id,
                        $file,
                        $line,
                        $subject,
                        $rule->message,
                    );
                    if ('forbid_calls' === $rule->type) {
                        foreach ($references['calls'] as $call) {
                            if (\in_array($call['method'], $rule->methods, true)) {
                                $violations[] = $make($call['line'], '->' . $call['method'] . '()');
                            }
                        }
                        continue;
                    }
                    $seen = [];
                    foreach ($references['names'] as $reference) {
                        $name = $reference['name'];
                        if (isset($seen[strtolower($name)]) || !$this->breaks($config, $layers, $rule, $context, $layer, $name)) {
                            continue;
                        }
                        $seen[strtolower($name)] = true;
                        $violations[] = $make($reference['line'], $name);
                    }
                }
            }
        }

        usort($violations, static fn (LayerViolation $a, LayerViolation $b): int => [$a->file, $a->line, $a->rule, $a->subject] <=> [$b->file, $b->line, $b->rule, $b->subject]);

        return ['violations' => $violations, 'files' => $files];
    }

    private function breaks(ModuleBoundaryConfig $config, LayerConfig $layers, LayerRule $rule, string $context, ?string $layer, string $name): bool
    {
        if ('forbid_names' === $rule->type) {
            return LayerRule::nameMatchesAny($name, $rule->names) && !LayerRule::nameMatchesAny($name, $rule->allow);
        }

        $targetModule = $this->moduleOfName($config, $name);
        if (null === $targetModule) {
            return false;
        }
        $targetLayer = $this->layerOfName($name, $config->modules[$targetModule]['namespace'], $layers->layersOf($targetModule));
        if (null === $targetLayer
            || ('same' === $rule->scope && $targetModule !== $context)
            || ('other' === $rule->scope && $targetModule === $context)) {
            return false;
        }

        return 'forbid_layers' === $rule->type
            ? \in_array($targetLayer, $rule->to, true)
            : $targetLayer !== $layer && !\in_array($targetLayer, $rule->to, true);
    }

    /**
     * @param array<string, array{paths: list<string>, namespaces: list<string>}> $definitions
     */
    private function layerOfPath(string $file, string $modulePath, array $definitions): ?string
    {
        $best = null;
        $bestLength = -1;
        foreach ($definitions as $layer => $mapping) {
            foreach ($mapping['paths'] as $path) {
                $full = ('' === $modulePath ? '' : $modulePath . '/') . $path;
                if (ModuleBoundaryChecker::pathMatches($file, $full) && \strlen($full) > $bestLength) {
                    $best = $layer;
                    $bestLength = \strlen($full);
                }
            }
        }

        return $best;
    }

    /**
     * @param array<string, array{paths: list<string>, namespaces: list<string>}> $definitions
     */
    private function layerOfName(string $name, string $moduleNamespace, array $definitions): ?string
    {
        $name = strtolower($name);
        $best = null;
        $bestLength = -1;
        foreach ($definitions as $layer => $mapping) {
            foreach ($mapping['namespaces'] as $namespace) {
                $full = strtolower($moduleNamespace . '\\' . $namespace);
                if (($name === $full || str_starts_with($name, $full . '\\')) && \strlen($full) > $bestLength) {
                    $best = $layer;
                    $bestLength = \strlen($full);
                }
            }
        }

        return $best;
    }

    private function moduleOfPath(ModuleBoundaryConfig $config, string $file): ?string
    {
        $best = null;
        $bestLength = -1;
        foreach ($config->modules as $name => $module) {
            $path = rtrim((string) $module['path'], '/');
            if (null !== $module['path'] && ModuleBoundaryChecker::pathMatches($file, $path) && \strlen($path) > $bestLength) {
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
     * PHP files (relative, sorted) below `$directory`, without the globally ignored ones.
     *
     * @param list<string> $ignore
     *
     * @return list<string>
     */
    private function phpFiles(string $root, string $directory, array $ignore): array
    {
        $absolute = '' === $directory ? $root : $root . '/' . $directory;
        if (!is_dir($absolute)) {
            return [];
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS));
        /** @var \SplFileInfo $info */
        foreach ($iterator as $info) {
            if (!$info->isFile() || 'php' !== $info->getExtension()) {
                continue;
            }
            $relative = ('' === $directory ? '' : $directory . '/') . str_replace('\\', '/', substr($info->getPathname(), \strlen($absolute) + 1));
            foreach ($ignore as $pattern) {
                if (ModuleBoundaryChecker::pathMatches($relative, $pattern)) {
                    continue 2;
                }
            }
            $files[] = $relative;
        }
        sort($files);

        return $files;
    }
}
