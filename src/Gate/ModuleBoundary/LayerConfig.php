<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * The optional `layers` section of a module-boundary map (since 1.12.0): layers inside declared contexts (modules),
 * the layer rules, the mode of each context and the baseline file.
 *
 * ```yaml
 * layers:
 *   definitions:            # layer => sub-directory of the module path (namespace derived), default <Layer>
 *     Domain: ~
 *     Application: ~
 *     Infrastructure: ~
 *     UI: { path: UI, namespace: UI }
 *   domain_allow: ['Doctrine\Common\Collections\']   # allow-list of the @default Domain rule
 *   contexts:               # module => enforce | warn (or { mode, layers: { <Layer>: <mapping> } })
 *     Uptime: enforce
 *     Billing: { mode: warn, layers: { UI: [UI, Controller, Form] } }
 *   rules: ['@default']     # default; '@default' expands to the four default rules, custom rules can follow
 *   baseline: config/quality/module-boundaries-baseline.txt
 * ```
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class LayerConfig
{
    public const DEFAULT_LAYERS = ['Domain', 'Application', 'Infrastructure', 'UI'];

    public const DEFAULT_DOMAIN_ALLOW = ['Doctrine\\Common\\Collections\\'];

    public const DEFAULT_BASELINE = 'config/quality/module-boundaries-baseline.txt';

    public const MODES = ['enforce', 'warn'];

    private const KEYS = ['definitions', 'domain_allow', 'contexts', 'rules', 'baseline'];

    private const RULE_KEYS = [
        'forbid_layers' => ['to', 'scope'],
        'allow_layers' => ['to', 'scope'],
        'forbid_names' => ['names', 'allow'],
        'forbid_calls' => ['methods'],
    ];

    /**
     * @param array<string, array{paths: list<string>, namespaces: list<string>}>                                             $definitions global layer mapping
     * @param array<string, array{mode: string, layers: array<string, array{paths: list<string>, namespaces: list<string>}>}> $contexts    module => mode + effective mapping
     * @param list<LayerRule>                                                                                                 $rules
     */
    public function __construct(
        public array $definitions,
        public array $contexts,
        public array $rules,
        public string $baseline = self::DEFAULT_BASELINE,
    ) {
    }

    /**
     * Layer mapping of a module: its context override, else the global definitions.
     *
     * @return array<string, array{paths: list<string>, namespaces: list<string>}>
     */
    public function layersOf(string $module): array
    {
        return $this->contexts[$module]['layers'] ?? $this->definitions;
    }

    /**
     * The `@default` rule set.
     *
     * @param list<string> $domainAllow
     *
     * @return list<LayerRule>
     */
    public static function defaultRules(array $domainAllow = self::DEFAULT_DOMAIN_ALLOW): array
    {
        return [
            new LayerRule(
                'domain-layer-edges',
                'forbid_layers',
                in: ['Domain'],
                to: ['UI', 'Infrastructure'],
                message: 'Domain must not depend on the UI or Infrastructure layer of any context.',
            ),
            new LayerRule(
                'domain-framework-free',
                'forbid_names',
                in: ['Domain'],
                names: ['Symfony\\', 'Doctrine\\'],
                allow: $domainAllow,
                message: 'Domain is plain PHP: no Symfony, no Doctrine (except layers.domain_allow).',
            ),
            new LayerRule(
                'ui-no-persistence',
                'forbid_names',
                in: ['UI'],
                names: [
                    'Doctrine\\ORM\\EntityManagerInterface',
                    'Doctrine\\ORM\\EntityManager',
                    'Doctrine\\Persistence\\ObjectManager',
                    'Doctrine\\Persistence\\ManagerRegistry',
                    '*Repository',
                    '*RepositoryInterface',
                ],
                message: 'UI must not use repositories or the entity manager directly (call an Application use case).',
            ),
            new LayerRule(
                'flush-in-application',
                'forbid_calls',
                notIn: ['Application', 'Infrastructure'],
                methods: ['flush'],
                message: 'flush() belongs to the Application (transaction per use case) or Infrastructure layer.',
            ),
        ];
    }

    /**
     * @param array<string, array{namespace: string, path: string|null}> $modules
     */
    public static function fromArray(mixed $data, array $modules): self
    {
        if (!\is_array($data)) {
            throw new ConfigException('layers must be a map.');
        }
        $unknown = array_diff(array_keys($data), self::KEYS);
        if ([] !== $unknown) {
            throw new ConfigException('layers: unknown key(s) ' . implode(', ', $unknown) . ' (allowed: ' . implode(', ', self::KEYS) . ').');
        }

        $definitions = [];
        $rawDefinitions = $data['definitions'] ?? array_fill_keys(self::DEFAULT_LAYERS, null);
        foreach (self::map($rawDefinitions, 'layers.definitions') as $layer => $mapping) {
            $definitions[$layer] = self::mapping($layer, $mapping, "layers.definitions.{$layer}");
        }
        if ([] === $definitions) {
            throw new ConfigException('layers.definitions must define at least one layer.');
        }

        $contexts = [];
        foreach (self::map($data['contexts'] ?? [], 'layers.contexts') as $module => $context) {
            $where = "layers.contexts.{$module}";
            if (!isset($modules[$module])) {
                throw new ConfigException(\sprintf('%s references unknown module "%s".', $where, $module));
            }
            if (null === $modules[$module]['path']) {
                throw new ConfigException("{$where}: module {$module} needs a \"path\" to hold layers.");
            }
            if (\is_string($context)) {
                $context = ['mode' => $context];
            }
            if (!\is_array($context)) {
                throw new ConfigException("{$where} must be \"enforce\", \"warn\" or a map with mode / layers.");
            }
            $extra = array_diff(array_keys($context), ['mode', 'layers']);
            if ([] !== $extra) {
                throw new ConfigException("{$where}: unknown key(s) " . implode(', ', $extra) . ' (allowed: mode, layers).');
            }
            $mode = $context['mode'] ?? 'warn';
            if (!\in_array($mode, self::MODES, true)) {
                throw new ConfigException("{$where}.mode must be \"enforce\" or \"warn\".");
            }
            $layers = $definitions;
            foreach (self::map($context['layers'] ?? [], "{$where}.layers") as $layer => $mapping) {
                if (!isset($definitions[$layer])) {
                    throw new ConfigException(\sprintf('%s.layers references unknown layer "%s".', $where, $layer));
                }
                $layers[$layer] = self::mapping($layer, $mapping, "{$where}.layers.{$layer}");
            }
            $contexts[$module] = ['mode' => $mode, 'layers' => $layers];
        }

        $domainAllow = self::stringList($data['domain_allow'] ?? self::DEFAULT_DOMAIN_ALLOW, 'layers.domain_allow', true);

        $rules = [];
        $ids = [];
        foreach (self::list($data['rules'] ?? ['@default'], 'layers.rules') as $index => $rule) {
            $added = '@default' === $rule
                ? self::defaultRules($domainAllow)
                : [self::rule($rule, "layers.rules[{$index}]", $definitions, $contexts)];
            foreach ($added as $item) {
                if (isset($ids[$item->id])) {
                    throw new ConfigException("layers.rules[{$index}]: duplicate rule id \"{$item->id}\".");
                }
                $ids[$item->id] = true;
                $rules[] = $item;
            }
        }

        $baseline = $data['baseline'] ?? self::DEFAULT_BASELINE;
        if (!\is_string($baseline) || '' === trim($baseline)) {
            throw new ConfigException('layers.baseline must be a non-empty path.');
        }

        return new self($definitions, $contexts, $rules, $baseline);
    }

    /**
     * @param array<string, array{paths: list<string>, namespaces: list<string>}>                                             $definitions
     * @param array<string, array{mode: string, layers: array<string, array{paths: list<string>, namespaces: list<string>}>}> $contexts
     */
    private static function rule(mixed $rule, string $where, array $definitions, array $contexts): LayerRule
    {
        if (!\is_array($rule) || !isset($rule['type']) || !\is_string($rule['type'])) {
            throw new ConfigException("{$where} must be '@default' or a map with \"id\" and \"type\".");
        }
        $type = $rule['type'];
        if (!isset(self::RULE_KEYS[$type])) {
            throw new ConfigException("{$where}.type \"{$type}\" is unknown (allowed: " . implode(', ', LayerRule::TYPES) . ').');
        }
        $allowed = ['id', 'type', 'in', 'not_in', 'contexts', 'message', ...self::RULE_KEYS[$type]];
        $extra = array_diff(array_keys($rule), $allowed);
        if ([] !== $extra) {
            throw new ConfigException("{$where} ({$type}): unknown key(s) " . implode(', ', $extra) . ' (allowed: ' . implode(', ', $allowed) . ').');
        }
        $id = $rule['id'] ?? null;
        if (!\is_string($id) || 1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $id)) {
            throw new ConfigException("{$where} needs an \"id\" ([A-Za-z0-9_.-], used as the baseline key).");
        }
        if (isset($rule['in']) === isset($rule['not_in'])) {
            throw new ConfigException("{$where} ({$id}) needs exactly one of \"in\" or \"not_in\" (source layers).");
        }
        $in = isset($rule['in']) ? self::layerList($rule['in'], "{$where}.in", $definitions) : [];
        $notIn = isset($rule['not_in']) ? self::layerList($rule['not_in'], "{$where}.not_in", $definitions) : [];
        $ruleContexts = isset($rule['contexts']) ? self::stringList($rule['contexts'], "{$where}.contexts") : [];
        foreach ($ruleContexts as $context) {
            if (!isset($contexts[$context])) {
                throw new ConfigException(\sprintf('%s.contexts references undeclared context "%s".', $where, $context));
            }
        }
        $message = $rule['message'] ?? '';
        if (!\is_string($message)) {
            throw new ConfigException("{$where}.message must be a string.");
        }

        $to = [];
        $scope = 'any';
        $names = [];
        $allow = [];
        $methods = [];
        if ('forbid_names' === $type) {
            $names = self::stringList($rule['names'] ?? null, "{$where}.names");
            $allow = self::stringList($rule['allow'] ?? [], "{$where}.allow", true);
        } elseif ('forbid_calls' === $type) {
            foreach (self::stringList($rule['methods'] ?? null, "{$where}.methods") as $method) {
                if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $method)) {
                    throw new ConfigException("{$where}.methods: \"{$method}\" is not a method name.");
                }
                $methods[] = strtolower($method);
            }
        } else {
            $to = self::layerList($rule['to'] ?? null, "{$where}.to", $definitions);
            $scope = $rule['scope'] ?? 'any';
            if (!\in_array($scope, LayerRule::SCOPES, true)) {
                throw new ConfigException("{$where}.scope must be any, same or other.");
            }
        }

        return new LayerRule($id, $type, $in, $notIn, $ruleContexts, $to, $scope, $names, $allow, $methods, $message);
    }

    /**
     * `~` (= the layer name), a sub-directory or list of them (namespaces derived), or `{ path, namespace }`.
     *
     * @return array{paths: list<string>, namespaces: list<string>}
     */
    private static function mapping(string $layer, mixed $mapping, string $where): array
    {
        if (null === $mapping) {
            $mapping = ['path' => $layer];
        } elseif (!\is_array($mapping) || array_is_list($mapping)) {
            $mapping = ['path' => $mapping];
        }
        $extra = array_diff(array_keys($mapping), ['path', 'namespace']);
        if ([] !== $extra) {
            throw new ConfigException("{$where}: unknown key(s) " . implode(', ', $extra) . ' (allowed: path, namespace).');
        }
        $paths = [];
        foreach (self::stringList($mapping['path'] ?? [], "{$where}.path", true) as $path) {
            $path = trim(str_replace('\\', '/', $path), '/');
            if ('' === $path || \in_array('..', explode('/', $path), true) || \in_array('.', explode('/', $path), true)) {
                throw new ConfigException("{$where}.path must be sub-directories of the module path (no '.' or '..').");
            }
            $paths[] = $path;
        }
        $namespaces = [];
        foreach (self::stringList($mapping['namespace'] ?? [], "{$where}.namespace", true) as $namespace) {
            $namespaces[] = trim($namespace, '\\');
        }
        if ([] === $paths && [] === $namespaces) {
            throw new ConfigException("{$where} needs a path or a namespace.");
        }
        if ([] === $paths) {
            $paths = array_map(static fn (string $ns): string => str_replace('\\', '/', $ns), $namespaces);
        }
        if ([] === $namespaces) {
            $namespaces = array_map(static fn (string $path): string => str_replace('/', '\\', $path), $paths);
        }

        return ['paths' => $paths, 'namespaces' => $namespaces];
    }

    /**
     * @param array<string, array{paths: list<string>, namespaces: list<string>}> $definitions
     *
     * @return list<string>
     */
    private static function layerList(mixed $value, string $where, array $definitions): array
    {
        $layers = self::stringList($value, $where);
        foreach ($layers as $layer) {
            if (!isset($definitions[$layer])) {
                throw new ConfigException(\sprintf('%s references unknown layer "%s".', $where, $layer));
            }
        }

        return $layers;
    }

    /**
     * @return array<string, mixed>
     */
    private static function map(mixed $value, string $where): array
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw new ConfigException("{$where} must be a map keyed by name.");
        }
        $map = [];
        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
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

        return array_values($value);
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $where, bool $allowEmpty = false): array
    {
        if (\is_string($value)) {
            $value = [$value];
        }
        if (!\is_array($value) || (!$allowEmpty && [] === $value)) {
            throw new ConfigException("{$where} must be a non-empty string or list of strings.");
        }
        $strings = [];
        foreach ($value as $item) {
            if (!\is_string($item) || '' === trim($item, '\\ ')) {
                throw new ConfigException("{$where} must contain non-empty strings.");
            }
            $strings[] = $item;
        }

        return $strings;
    }
}
