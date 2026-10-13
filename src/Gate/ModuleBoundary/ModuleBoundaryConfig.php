<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Module-boundary map loaded from YAML (needs `symfony/yaml`) or a PHP file returning an array.
 *
 * ```yaml
 * paths: [src]                 # scan roots for edge checks (default: the module paths)
 * ignore: ['src/Legacy/']      # never scanned (exact path, directory prefix, or fnmatch glob)
 * references: use              # use = top-level imports only; all = also \Fully\Qualified names
 * modules:
 *   Shared: { namespace: 'App\Shared', path: src/Shared }
 * allowed_edges:               # allow-list: a listed module may only import these modules
 *   Shared: []
 * forbidden_edges:             # deny-list with reviewed exceptions
 *   - { from: Project, to: [Ops], except: [src/Project/Legacy.php], message: '...' }
 * rules:                       # see Rule for the types
 *   - { type: require_path, path: src/Kernel.php }
 * ```
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ModuleBoundaryConfig
{
    private const TOP_LEVEL_KEYS = ['paths', 'ignore', 'references', 'modules', 'allowed_edges', 'forbidden_edges', 'rules'];

    private const RULE_KEYS = [
        'forbid_path' => ['path'],
        'require_path' => ['path'],
        'forbid_import' => ['in', 'except', 'imports'],
        'forbid_pattern' => ['in', 'except', 'pattern'],
        'require_pattern' => ['path', 'pattern'],
        'require_near' => ['path', 'pattern', 'requires', 'within_lines'],
    ];

    private const REQUIRED_RULE_KEYS = [
        'forbid_path' => ['path'],
        'require_path' => ['path'],
        'forbid_import' => ['imports'],
        'forbid_pattern' => ['pattern'],
        'require_pattern' => ['path', 'pattern'],
        'require_near' => ['path', 'pattern', 'requires'],
    ];

    /**
     * @param array<string, array{namespace: string, path: string|null}> $modules
     * @param array<string, list<string>>                                $allowedEdges
     * @param list<ForbiddenEdge>                                        $forbiddenEdges
     * @param list<Rule>                                                 $rules
     * @param list<string>                                               $ignore
     * @param list<string>                                               $paths
     */
    public function __construct(
        public readonly array $modules = [],
        public readonly array $allowedEdges = [],
        public readonly array $forbiddenEdges = [],
        public readonly array $rules = [],
        public readonly array $ignore = [],
        public readonly array $paths = [],
        public readonly bool $includeFullyQualified = false,
    ) {
    }

    public static function fromFile(string $file): self
    {
        if (!\is_file($file)) {
            throw new ConfigException("Module boundary config not found: {$file}");
        }

        $extension = \strtolower(\pathinfo($file, \PATHINFO_EXTENSION));
        if ('php' === $extension) {
            $data = (static fn (string $path): mixed => require $path)($file);
        } elseif ('yaml' === $extension || 'yml' === $extension) {
            if (!\class_exists(Yaml::class)) {
                // symfony/yaml is a dev dependency of this package, so this branch never runs here.
                // @codeCoverageIgnoreStart
                throw new ConfigException('Reading a YAML config needs symfony/yaml (composer require --dev symfony/yaml), or use a PHP file returning an array.');
                // @codeCoverageIgnoreEnd
            }

            try {
                $data = Yaml::parseFile($file);
            } catch (ParseException $e) {
                throw new ConfigException("Invalid YAML in {$file}: " . $e->getMessage(), 0, $e);
            }
        } else {
            throw new ConfigException("Unsupported config format for {$file} (use .yaml, .yml or .php).");
        }

        if (!\is_array($data)) {
            throw new ConfigException("{$file} must define a map (got " . get_debug_type($data) . ').');
        }

        try {
            return self::fromArray($data);
        } catch (ConfigException $e) {
            throw new ConfigException("{$file}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $unknown = \array_diff(\array_keys($data), self::TOP_LEVEL_KEYS);
        if ([] !== $unknown) {
            throw new ConfigException('Unknown key(s): ' . \implode(', ', $unknown) . ' (allowed: ' . \implode(', ', self::TOP_LEVEL_KEYS) . ').');
        }

        $modules = [];
        foreach (self::map($data['modules'] ?? [], 'modules') as $name => $definition) {
            if (\is_string($definition)) {
                $definition = ['namespace' => $definition];
            }
            if (!\is_array($definition) || !isset($definition['namespace']) || !\is_string($definition['namespace']) || '' === \trim($definition['namespace'], '\\')) {
                throw new ConfigException("modules.{$name} needs a non-empty \"namespace\" string.");
            }
            $extra = \array_diff(\array_keys($definition), ['namespace', 'path']);
            if ([] !== $extra) {
                throw new ConfigException("modules.{$name}: unknown key(s) " . \implode(', ', $extra) . ' (allowed: namespace, path).');
            }
            $path = $definition['path'] ?? null;
            if (null !== $path && (!\is_string($path) || '' === $path)) {
                throw new ConfigException("modules.{$name}.path must be a non-empty string.");
            }
            $modules[$name] = [
                'namespace' => \trim($definition['namespace'], '\\'),
                'path' => null === $path ? null : self::normalizePath($path),
            ];
        }

        $allowedEdges = [];
        foreach (self::map($data['allowed_edges'] ?? [], 'allowed_edges') as $from => $targets) {
            self::assertModule($modules, $from, 'allowed_edges');
            $allowedEdges[$from] = self::moduleList($modules, $targets ?? [], "allowed_edges.{$from}");
        }

        $forbiddenEdges = [];
        foreach (self::list($data['forbidden_edges'] ?? [], 'forbidden_edges') as $index => $edge) {
            $where = "forbidden_edges[{$index}]";
            if (!\is_array($edge)) {
                throw new ConfigException("{$where} must be a map with from / to.");
            }
            $extra = \array_diff(\array_keys($edge), ['from', 'to', 'except', 'message']);
            if ([] !== $extra) {
                throw new ConfigException("{$where}: unknown key(s) " . \implode(', ', $extra) . ' (allowed: from, to, except, message).');
            }
            if (!isset($edge['from'], $edge['to'])) {
                throw new ConfigException("{$where} needs \"from\" and \"to\".");
            }
            $forbiddenEdges[] = new ForbiddenEdge(
                self::moduleList($modules, $edge['from'], "{$where}.from"),
                self::moduleList($modules, $edge['to'], "{$where}.to"),
                self::paths($edge['except'] ?? [], "{$where}.except"),
                self::message($edge['message'] ?? '', "{$where}.message"),
            );
        }

        $rules = [];
        foreach (self::list($data['rules'] ?? [], 'rules') as $index => $rule) {
            $rules[] = self::rule($rule, "rules[{$index}]");
        }

        $references = $data['references'] ?? 'use';
        if ('use' !== $references && 'all' !== $references) {
            throw new ConfigException('references must be "use" or "all".');
        }

        $paths = self::paths($data['paths'] ?? [], 'paths');
        if ([] === $paths) {
            foreach ($modules as $module) {
                if (null !== $module['path'] && !\in_array($module['path'], $paths, true)) {
                    $paths[] = $module['path'];
                }
            }
        }

        return new self(
            $modules,
            $allowedEdges,
            $forbiddenEdges,
            $rules,
            self::paths($data['ignore'] ?? [], 'ignore'),
            $paths,
            'all' === $references,
        );
    }

    /**
     * Compiles a delimiter-less PCRE pattern in multi-line mode.
     */
    public static function compilePattern(string $pattern, string $where): string
    {
        $regex = '~' . \str_replace('~', '\~', $pattern) . '~m';
        if (false === @\preg_match($regex, '')) {
            throw new ConfigException("{$where} is not a valid regular expression: {$pattern}");
        }

        return $regex;
    }

    private static function rule(mixed $rule, string $where): Rule
    {
        if (!\is_array($rule) || !isset($rule['type']) || !\is_string($rule['type'])) {
            throw new ConfigException("{$where} must be a map with a \"type\".");
        }
        $type = $rule['type'];
        if (!isset(self::RULE_KEYS[$type])) {
            throw new ConfigException("{$where}.type \"{$type}\" is unknown (allowed: " . \implode(', ', Rule::TYPES) . ').');
        }
        $allowed = [...self::RULE_KEYS[$type], 'type', 'message'];
        $extra = \array_diff(\array_keys($rule), $allowed);
        if ([] !== $extra) {
            throw new ConfigException("{$where} ({$type}): unknown key(s) " . \implode(', ', $extra) . ' (allowed: ' . \implode(', ', $allowed) . ').');
        }
        foreach (self::REQUIRED_RULE_KEYS[$type] as $key) {
            if (!isset($rule[$key])) {
                throw new ConfigException("{$where} ({$type}) needs \"{$key}\".");
            }
        }

        $withinLines = $rule['within_lines'] ?? 0;
        if (!\is_int($withinLines) || $withinLines < 0) {
            throw new ConfigException("{$where}.within_lines must be a non-negative integer.");
        }

        $imports = [];
        foreach (self::stringList($rule['imports'] ?? [], "{$where}.imports") as $import) {
            $imports[] = ltrim($import, '\\');
        }

        return new Rule(
            $type,
            self::message($rule['message'] ?? '', "{$where}.message"),
            self::paths($rule['path'] ?? [], "{$where}.path"),
            self::paths($rule['in'] ?? [], "{$where}.in"),
            self::paths($rule['except'] ?? [], "{$where}.except"),
            $imports,
            isset($rule['pattern']) ? self::compilePattern(self::string($rule['pattern'], "{$where}.pattern"), "{$where}.pattern") : null,
            isset($rule['requires']) ? self::compilePattern(self::string($rule['requires'], "{$where}.requires"), "{$where}.requires") : null,
            $withinLines,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function map(mixed $value, string $where): array
    {
        if (!\is_array($value)) {
            throw new ConfigException("{$where} must be a map.");
        }
        $map = [];
        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new ConfigException("{$where} must be a map keyed by name.");
            }
            $map[$key] = $item;
        }

        return $map;
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value, string $where): array
    {
        if (!\is_array($value)) {
            throw new ConfigException("{$where} must be a list.");
        }

        return \array_values($value);
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $where): array
    {
        if (\is_string($value)) {
            $value = [$value];
        }
        $strings = [];
        foreach (self::list($value, $where) as $item) {
            $strings[] = self::string($item, $where);
        }

        return $strings;
    }

    private static function string(mixed $value, string $where): string
    {
        if (!\is_string($value) || '' === $value) {
            throw new ConfigException("{$where} must contain non-empty strings.");
        }

        return $value;
    }

    private static function message(mixed $value, string $where): string
    {
        if (!\is_string($value)) {
            throw new ConfigException("{$where} must be a string.");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function paths(mixed $value, string $where): array
    {
        $paths = [];
        foreach (self::stringList($value, $where) as $path) {
            $paths[] = self::normalizePath($path);
        }

        return $paths;
    }

    /**
     * @param array<string, array{namespace: string, path: string|null}> $modules
     *
     * @return list<string>
     */
    private static function moduleList(array $modules, mixed $value, string $where): array
    {
        $names = self::stringList($value, $where);
        foreach ($names as $name) {
            self::assertModule($modules, $name, $where);
        }

        return $names;
    }

    /**
     * @param array<string, array{namespace: string, path: string|null}> $modules
     */
    private static function assertModule(array $modules, string $name, string $where): void
    {
        if (!isset($modules[$name])) {
            throw new ConfigException(\sprintf('%s references unknown module "%s".', $where, $name));
        }
    }

    /** Forward slashes, no leading `./` (`.` is the root, ''); a trailing `/` (directory prefix) is kept. */
    private static function normalizePath(string $path): string
    {
        $path = \str_replace('\\', '/', $path);
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        if ('.' === $path) {
            return '';
        }
        $trailing = str_ends_with($path, '/');
        $path = \rtrim($path, '/');

        return $trailing && '' !== $path ? $path . '/' : $path;
    }
}
