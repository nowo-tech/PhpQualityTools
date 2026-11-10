<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ConfigException;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\LayerConfig;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\LayerRule;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ModuleBoundaryConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class LayerConfigTest extends TestCase
{
    private const MODULES = [
        'Uptime' => ['namespace' => 'App\\Uptime', 'path' => 'src/Uptime'],
        'Billing' => ['namespace' => 'App\\Billing', 'path' => 'src/Billing'],
        'Psr' => ['namespace' => 'Psr', 'path' => null],
    ];

    public function testWithoutLayersSectionNothingChanges(): void
    {
        self::assertNull(ModuleBoundaryConfig::fromArray(['modules' => ['A' => 'A']])->layers);
    }

    public function testDefaults(): void
    {
        $config = LayerConfig::fromArray(['contexts' => ['Uptime' => 'enforce', 'Billing' => ['layers' => ['UI' => ['UI', 'Controller']]]]], self::MODULES);

        $default = ['paths' => ['Domain'], 'namespaces' => ['Domain']];
        self::assertSame(LayerConfig::DEFAULT_LAYERS, array_keys($config->definitions));
        self::assertSame($default, $config->definitions['Domain']);
        self::assertSame('enforce', $config->contexts['Uptime']['mode']);
        self::assertSame('warn', $config->contexts['Billing']['mode']);
        self::assertSame(['paths' => ['UI', 'Controller'], 'namespaces' => ['UI', 'Controller']], $config->layersOf('Billing')['UI']);
        self::assertSame($default, $config->layersOf('Billing')['Domain']);
        self::assertSame($config->definitions, $config->layersOf('Psr'));
        self::assertSame(LayerConfig::DEFAULT_BASELINE, $config->baseline);
        self::assertSame(
            ['domain-layer-edges', 'domain-framework-free', 'ui-no-persistence', 'flush-in-application'],
            array_map(static fn (LayerRule $r): string => $r->id, $config->rules),
        );
        self::assertSame(['Doctrine\\Common\\Collections\\'], $config->rules[1]->allow);
    }

    public function testMappingsCustomRulesAndDomainAllow(): void
    {
        $config = LayerConfig::fromArray([
            'definitions' => [
                'Core' => 'Model/Core',
                'Web' => ['namespace' => '\\Http\\Web\\'],
                'Infra' => ['path' => ['Infra', 'Persistence'], 'namespace' => 'Infra'],
            ],
            'domain_allow' => [],
            'contexts' => ['Uptime' => ['mode' => 'enforce']],
            'rules' => [
                ['id' => 'core-calls', 'type' => 'forbid_calls', 'not_in' => 'Infra', 'methods' => ['Flush', 'persist'], 'contexts' => 'Uptime'],
                ['id' => 'core.only', 'type' => 'allow_layers', 'in' => ['Core'], 'to' => 'Core', 'scope' => 'other', 'message' => 'm'],
                ['id' => 'no-psr', 'type' => 'forbid_names', 'in' => 'Web', 'names' => 'Psr\\', 'allow' => 'Psr\\Log\\'],
            ],
            'baseline' => 'var/baseline.txt',
        ], self::MODULES);

        self::assertSame(['paths' => ['Model/Core'], 'namespaces' => ['Model\\Core']], $config->definitions['Core']);
        self::assertSame(['paths' => ['Http/Web'], 'namespaces' => ['Http\\Web']], $config->definitions['Web']);
        self::assertSame(['paths' => ['Infra', 'Persistence'], 'namespaces' => ['Infra']], $config->definitions['Infra']);
        self::assertSame('var/baseline.txt', $config->baseline);
        [$calls, $allow, $names] = $config->rules;
        self::assertSame(['flush', 'persist'], $calls->methods);
        self::assertSame(['Infra'], $calls->notIn);
        self::assertSame(['Uptime'], $calls->contexts);
        self::assertSame(['allow_layers', ['Core'], ['Core'], 'other', 'm'], [$allow->type, $allow->in, $allow->to, $allow->scope, $allow->message]);
        self::assertSame([['Psr\\'], ['Psr\\Log\\']], [$names->names, $names->allow]);

        $empty = LayerConfig::fromArray(['domain_allow' => [], 'rules' => ['@default']], self::MODULES);
        self::assertSame([], $empty->rules[1]->allow);
        self::assertSame([], $empty->contexts);
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidConfigs(mixed $layers, string $expected): void
    {
        try {
            ModuleBoundaryConfig::fromArray(['modules' => self::MODULES, 'layers' => $layers]);
            self::fail('ConfigException expected');
        } catch (ConfigException $e) {
            self::assertStringContainsString($expected, $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidProvider(): iterable
    {
        $rule = static fn (array $extra): array => ['rules' => [['id' => 'r', 'type' => 'forbid_calls', 'in' => 'Domain', 'methods' => 'flush', ...$extra]]];

        yield 'not a map' => [null, 'layers must be a map.'];
        yield 'unknown key' => [['context' => []], 'layers: unknown key(s) context'];
        yield 'definitions list' => [['definitions' => ['Domain']], 'layers.definitions must be a map keyed by name.'];
        yield 'definitions empty' => [['definitions' => []], 'layers.definitions must define at least one layer.'];
        yield 'mapping scalar' => [['definitions' => ['D' => 5]], 'layers.definitions.D.path must be a non-empty string or list of strings.'];
        yield 'mapping list of scalars' => [['definitions' => ['D' => [true]]], 'layers.definitions.D.path must contain non-empty strings.'];
        yield 'mapping unknown key' => [['definitions' => ['D' => ['dir' => 'x']]], 'layers.definitions.D: unknown key(s) dir'];
        yield 'mapping null namespace' => [['definitions' => ['D' => ['namespace' => null]]], 'layers.definitions.D needs a path or a namespace.'];
        yield 'mapping dot dot' => [['definitions' => ['D' => '../x']], "layers.definitions.D.path must be sub-directories of the module path (no '.' or '..')."];
        yield 'mapping dot' => [['definitions' => ['D' => './x']], "no '.' or '..'"];
        yield 'mapping empty path' => [['definitions' => ['D' => '/']], "no '.' or '..'"];
        yield 'mapping empty list' => [['definitions' => ['D' => []]], 'layers.definitions.D needs a path or a namespace.'];
        yield 'unknown context module' => [['contexts' => ['Nope' => 'warn']], 'layers.contexts.Nope references unknown module "Nope".'];
        yield 'context without path' => [['contexts' => ['Psr' => 'warn']], 'layers.contexts.Psr: module Psr needs a "path" to hold layers.'];
        yield 'context scalar' => [['contexts' => ['Uptime' => 3]], 'layers.contexts.Uptime must be "enforce", "warn" or a map'];
        yield 'context unknown key' => [['contexts' => ['Uptime' => ['strict' => true]]], 'layers.contexts.Uptime: unknown key(s) strict'];
        yield 'context bad mode' => [['contexts' => ['Uptime' => 'fail']], 'layers.contexts.Uptime.mode must be "enforce" or "warn".'];
        yield 'context unknown layer' => [['contexts' => ['Uptime' => ['layers' => ['Web' => 'Web']]]], 'layers.contexts.Uptime.layers references unknown layer "Web".'];
        yield 'domain_allow scalar' => [['domain_allow' => 1], 'layers.domain_allow must be a non-empty string or list of strings.'];
        yield 'rules map' => [['rules' => 'x'], 'layers.rules must be a list.'];
        yield 'rule not a map' => [['rules' => ['@defaults']], "layers.rules[0] must be '@default' or a map with \"id\" and \"type\"."];
        yield 'rule unknown type' => [['rules' => [['id' => 'r', 'type' => 'forbid_edges']]], 'layers.rules[0].type "forbid_edges" is unknown (allowed: forbid_layers, allow_layers, forbid_names, forbid_calls).'];
        yield 'rule unknown key' => [$rule(['to' => 'UI']), 'layers.rules[0] (forbid_calls): unknown key(s) to'];
        yield 'rule bad id' => [$rule(['id' => 'a b']), 'layers.rules[0] needs an "id"'];
        yield 'rule missing id' => [['rules' => [['type' => 'forbid_calls']]], 'layers.rules[0] needs an "id"'];
        yield 'rule in and not_in' => [$rule(['not_in' => 'UI']), 'layers.rules[0] (r) needs exactly one of "in" or "not_in"'];
        yield 'rule unknown layer' => [$rule(['in' => 'Web']), 'layers.rules[0].in references unknown layer "Web".'];
        yield 'rule unknown context' => [$rule(['contexts' => 'Billing']), 'layers.rules[0].contexts references undeclared context "Billing".'];
        yield 'rule bad message' => [$rule(['message' => ['x']]), 'layers.rules[0].message must be a string.'];
        yield 'rule bad method' => [$rule(['methods' => 'do-it']), 'layers.rules[0].methods: "do-it" is not a method name.'];
        yield 'rule no methods' => [$rule(['methods' => []]), 'layers.rules[0].methods must be a non-empty string or list of strings.'];
        yield 'rule no names' => [['rules' => [['id' => 'r', 'type' => 'forbid_names', 'in' => 'UI']]], 'layers.rules[0].names must be a non-empty'];
        yield 'rule bad name' => [['rules' => [['id' => 'r', 'type' => 'forbid_names', 'in' => 'UI', 'names' => ['\\']]]], 'layers.rules[0].names must contain non-empty strings.'];
        yield 'rule no to' => [['rules' => [['id' => 'r', 'type' => 'forbid_layers', 'in' => 'UI']]], 'layers.rules[0].to must be a non-empty'];
        yield 'rule bad scope' => [['rules' => [['id' => 'r', 'type' => 'allow_layers', 'in' => 'UI', 'to' => 'UI', 'scope' => 'all']]], 'layers.rules[0].scope must be any, same or other.'];
        yield 'duplicate id' => [['rules' => ['@default', ['id' => 'flush-in-application', 'type' => 'forbid_calls', 'in' => 'UI', 'methods' => 'x']]], 'layers.rules[1]: duplicate rule id "flush-in-application".'];
        yield 'bad baseline' => [['baseline' => ' '], 'layers.baseline must be a non-empty path.'];
    }
}
