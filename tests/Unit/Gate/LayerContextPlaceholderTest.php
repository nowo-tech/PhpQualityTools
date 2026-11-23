<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ConfigException;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\LayerBaseline;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\LayerChecker;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\LayerConfig;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\LayerRule;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\LayerViolation;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ModuleBoundaryCommand;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ModuleBoundaryConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The `{context}` placeholder of `forbid_names` and the context selectors (`@all`, `@enforce`, `@warn`) of a layer rule
 * (since 1.15.0): one rule covers every context, and a context moved to `enforce` is covered without editing it.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class LayerContextPlaceholderTest extends TestCase
{
    use TempTreeTrait;

    private const BASELINE = 'config/quality/module-boundaries-baseline.txt';

    private const MODULES = [
        'Uptime' => ['namespace' => 'App\\Uptime', 'path' => 'src/Uptime'],
        'Billing' => ['namespace' => 'App\\Billing', 'path' => 'src/Billing'],
        'Identity' => ['namespace' => 'App\\Identity', 'path' => 'src/Identity'],
    ];

    private const ALLOW = ['App\\{context}\\Domain\\', 'App\\Shared\\Domain\\'];

    /**
     * @return array<string, string>
     */
    private function tree(): array
    {
        $domain = static fn (string $context, string $uses): string => "<?php\nnamespace App\\{$context}\\Domain;\n\n{$uses}\nfinal class Thing {}\n";

        return [
            'src/Uptime/Domain/Thing.php' => $domain('Uptime', "use App\\Uptime\\Domain\\Own;\nuse App\\Shared\\Domain\\Id;\nuse App\\Billing\\Domain\\Invoice;\nuse App\\Shared\\Util\\Clock;"),
            'src/Billing/Domain/Thing.php' => $domain('Billing', "use App\\Billing\\Domain\\Own;\nuse App\\Uptime\\Domain\\Monitor;\nuse App\\Shared\\Domain\\Id;"),
            'src/Identity/Domain/Thing.php' => $domain('Identity', "use App\\Identity\\Domain\\Own;\nuse App\\Billing\\Domain\\Invoice;"),
            'src/Identity/Application/Use.php' => "<?php\nnamespace App\\Identity\\Application;\n\nuse App\\Billing\\Domain\\Invoice;\n",
        ];
    }

    /**
     * @param array<string, string> $contexts
     * @param list<string>|string   $selector
     *
     * @return array<string, mixed>
     */
    private static function map(array|string $contexts, array|string $selector = '@enforce'): array
    {
        return [
            'modules' => self::MODULES,
            'layers' => [
                'contexts' => $contexts,
                'rules' => [[
                    'id' => 'domain-only-own-domain',
                    'type' => 'forbid_names',
                    'in' => 'Domain',
                    'contexts' => $selector,
                    'names' => 'App\\',
                    'allow' => self::ALLOW,
                ]],
                'baseline' => self::BASELINE,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $map
     *
     * @return list<string>
     */
    private function findings(array $map): array
    {
        $result = (new LayerChecker())->check(ModuleBoundaryConfig::fromArray($map), $this->makeTree($this->tree()));

        return array_map(static fn (LayerViolation $v): string => $v->context . ' ' . $v->mode . ' ' . $v->file . ':' . $v->line . ' ' . $v->subject, $result['violations']);
    }

    public function testPlaceholderExpandsPerContext(): void
    {
        $findings = $this->findings(self::map(['Uptime' => 'enforce', 'Billing' => 'enforce', 'Identity' => 'enforce']));

        // One rule: each context may import its own Domain and Shared\Domain, and nothing else of App\.
        self::assertSame([
            'Billing enforce src/Billing/Domain/Thing.php:5 App\\Uptime\\Domain\\Monitor',
            'Identity enforce src/Identity/Domain/Thing.php:5 App\\Billing\\Domain\\Invoice',
            'Uptime enforce src/Uptime/Domain/Thing.php:6 App\\Billing\\Domain\\Invoice',
            'Uptime enforce src/Uptime/Domain/Thing.php:7 App\\Shared\\Util\\Clock',
        ], $findings);
    }

    public function testRuleExpansionAccessors(): void
    {
        $rule = new LayerRule('r', 'forbid_names', in: ['Domain'], names: ['App\\{context}\\UI\\', 'Other\\'], allow: ['App\\{context}\\Domain\\']);

        self::assertSame(['App\\Billing\\UI\\', 'Other\\'], $rule->namesFor('Billing'));
        self::assertSame(['App\\Billing\\Domain\\'], $rule->allowFor('Billing'));
        self::assertSame(['a', 'b'], LayerRule::expand(['a', 'b'], 'Billing'), 'patterns without the placeholder are untouched');
    }

    public function testSelectorsPickContextsByMode(): void
    {
        $modes = ['Uptime' => 'enforce', 'Billing' => 'warn', 'Identity' => 'warn'];

        self::assertSame(['Uptime'], $this->contextsOf($this->findings(self::map($modes))));
        self::assertSame(['Billing', 'Identity'], $this->contextsOf($this->findings(self::map($modes, '@warn'))));
        self::assertSame(['Billing', 'Identity', 'Uptime'], $this->contextsOf($this->findings(self::map($modes, '@all'))));
        self::assertSame(['Billing'], $this->contextsOf($this->findings(self::map($modes, ['Billing']))));
        self::assertSame(['Billing'], $this->contextsOf($this->findings(self::map($modes, 'Billing'))));
    }

    public function testContextMovedToEnforceIsCoveredWithoutEditingTheRule(): void
    {
        $before = $this->findings(self::map(['Uptime' => 'enforce', 'Billing' => 'warn', 'Identity' => 'warn']));
        $after = $this->findings(self::map(['Uptime' => 'enforce', 'Billing' => 'enforce', 'Identity' => 'warn']));

        self::assertSame(['Uptime'], $this->contextsOf($before));
        self::assertSame(['Billing', 'Uptime'], $this->contextsOf($after));
        self::assertContains('Billing enforce src/Billing/Domain/Thing.php:5 App\\Uptime\\Domain\\Monitor', $after);
    }

    public function testSelectorWithoutMatchingContextChecksNothing(): void
    {
        self::assertSame([], $this->findings(self::map(['Uptime' => 'warn'])));
    }

    public function testRuleAppliesToHonoursTheMode(): void
    {
        $rule = new LayerRule('r', 'forbid_names', in: ['Domain'], names: ['x'], selector: '@enforce');

        self::assertTrue($rule->appliesTo('Uptime', 'Domain', 'enforce'));
        self::assertFalse($rule->appliesTo('Uptime', 'Domain', 'warn'));
        self::assertFalse($rule->appliesTo('Uptime', 'Domain'));
        self::assertTrue((new LayerRule('r', 'forbid_names', in: ['Domain'], names: ['x'], selector: '@all'))->appliesTo('Uptime', 'Domain', 'warn'));
        self::assertTrue((new LayerRule('r', 'forbid_names', in: ['Domain'], names: ['x']))->appliesTo('Uptime', 'Domain'), 'no selector: unchanged');
    }

    public function testConfigKeepsTheSelectorAndTheExplicitList(): void
    {
        $rules = $this->rulesOf(self::map(['Uptime' => 'enforce']));
        self::assertSame(['@enforce', []], [$rules[0]->selector, $rules[0]->contexts]);

        $rules = $this->rulesOf(self::map(['Uptime' => 'enforce'], ['Uptime']));
        self::assertSame([null, ['Uptime']], [$rules[0]->selector, $rules[0]->contexts]);
    }

    public function testDomainAllowMayUseThePlaceholder(): void
    {
        $config = LayerConfig::fromArray(['contexts' => ['Uptime' => 'enforce'], 'domain_allow' => ['App\\{context}\\Shared\\']], self::MODULES);

        self::assertSame(['App\\Uptime\\Shared\\'], $config->rules[1]->allowFor('Uptime'));
    }

    /**
     * @param array<string, mixed> $map
     */
    #[DataProvider('invalidProvider')]
    public function testInvalidConfigsFailWithAClearMessage(array $map, string $expected): void
    {
        try {
            ModuleBoundaryConfig::fromArray($map);
            self::fail('ConfigException expected');
        } catch (ConfigException $e) {
            self::assertStringContainsString($expected, $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidProvider(): iterable
    {
        $enforce = ['Uptime' => 'enforce'];

        yield 'unknown selector' => [self::map($enforce, '@enforced'), 'layers.rules[0].contexts: unknown selector "@enforced" (allowed: @all, @enforce, @warn).'];
        yield 'selector inside a list' => [self::map($enforce, ['@enforce']), 'selector "@enforce" must be given alone'];
        yield 'unknown context in a list' => [self::map($enforce, ['Billing']), 'layers.rules[0].contexts references undeclared context "Billing".'];
        yield 'unknown placeholder' => [self::withNames($enforce, ['App\\{module}\\Domain\\']), 'layers.rules[0].names: unknown placeholder "{module}" in "App\\{module}\\Domain\\" (only {context} exists).'];
        yield 'unknown placeholder in allow' => [self::withNames($enforce, ['App\\'], ['App\\{layer}\\']), 'layers.rules[0].allow: unknown placeholder "{layer}"'];
        yield 'placeholder without any context' => [self::withNames([], ['App\\{context}\\']), 'layers.rules[0].names: "App\\{context}\\" uses {context} but layers.contexts declares no context to expand it to.'];
        yield 'placeholder in domain_allow without any context' => [['modules' => self::MODULES, 'layers' => ['domain_allow' => ['App\\{context}\\']]], 'layers.domain_allow: "App\\{context}\\" uses {context}'];
    }

    /**
     * Baseline untouched: placeholder expansion keeps the rule id as the baseline key and the subject as the name
     * referenced, and `--strict-baseline` behaves as before (stale entries fail it, current ones pass).
     */
    public function testBaselineAndStrictBaselineAreUnaffected(): void
    {
        $tree = [...$this->tree(), 'config/quality/module-boundaries.yaml' => $this->yaml()];
        $root = $this->makeTree($tree);
        $baseline = $root . '/' . self::BASELINE;

        self::assertSame(0, $this->execute(['--update-baseline'], $root)[0]);
        $content = (string) file_get_contents($baseline);
        self::assertStringContainsString("\ndomain-only-own-domain|src/Billing/Domain/Thing.php|App\\Uptime\\Domain\\Monitor\n", $content);
        self::assertSame(4, array_sum(LayerBaseline::load($baseline)));

        [$code, $out, $err] = $this->execute(['--strict-baseline'], $root);
        self::assertSame([0, ''], [$code, $err]);
        self::assertStringContainsString('0 error(s), 0 warning(s), 4 baselined, 0 stale baseline entries.', $out);

        // A fixed finding is a stale entry: fatal with --strict-baseline, as without the placeholder.
        file_put_contents($root . '/src/Billing/Domain/Thing.php', "<?php\nnamespace App\\Billing\\Domain;\n");
        [$code, , $err] = $this->execute(['--strict-baseline'], $root);
        self::assertSame(1, $code);
        self::assertStringContainsString('error: baseline entry no longer occurs (remove it, or run --update-baseline): domain-only-own-domain|src/Billing/Domain/Thing.php|App\\Uptime\\Domain\\Monitor', $err);

        // A new finding in a context outside the selector stays unreported.
        file_put_contents($root . '/src/Billing/Domain/Thing.php', "<?php\nnamespace App\\Billing\\Domain;\nuse App\\Uptime\\Domain\\Monitor;\n");
        $yaml = str_replace('Billing: enforce', 'Billing: warn', $this->yaml());
        file_put_contents($root . '/config/quality/module-boundaries.yaml', $yaml);
        [$code, $out] = $this->execute([], $root);
        self::assertSame(0, $code, 'a warn context is not selected by @enforce');
        self::assertStringNotContainsString(' references ', $out);
    }

    public function testCommandFailsOnAnInvalidSelector(): void
    {
        $yaml = str_replace("'@enforce'", "'@strict'", $this->yaml());
        $root = $this->makeTree(['config/quality/module-boundaries.yaml' => $yaml]);

        [$code, , $err] = $this->execute([], $root);

        self::assertSame(2, $code);
        self::assertStringContainsString('unknown selector "@strict"', $err);
    }

    private function yaml(): string
    {
        return <<<'YAML'
            modules:
              Uptime: { namespace: 'App\Uptime', path: src/Uptime }
              Billing: { namespace: 'App\Billing', path: src/Billing }
              Identity: { namespace: 'App\Identity', path: src/Identity }
            layers:
              contexts: { Uptime: enforce, Billing: enforce, Identity: enforce }
              rules:
                - id: domain-only-own-domain
                  type: forbid_names
                  in: [Domain]
                  contexts: '@enforce'
                  names: ['App\']
                  allow: ['App\{context}\Domain\', 'App\Shared\Domain\']
            YAML;
    }

    /**
     * @param list<string>          $names
     * @param list<string>          $allow
     * @param array<string, string> $contexts
     *
     * @return array<string, mixed>
     */
    private static function withNames(array $contexts, array $names, array $allow = []): array
    {
        $map = self::map($contexts);
        $map['layers']['rules'][0]['names'] = $names;
        $map['layers']['rules'][0]['allow'] = $allow;

        return $map;
    }

    /**
     * @param array<string, mixed> $map
     *
     * @return list<LayerRule>
     */
    private function rulesOf(array $map): array
    {
        $layers = ModuleBoundaryConfig::fromArray($map)->layers;
        self::assertInstanceOf(LayerConfig::class, $layers);

        return $layers->rules;
    }

    /**
     * @param list<string> $findings
     *
     * @return list<string>
     */
    private function contextsOf(array $findings): array
    {
        $contexts = array_values(array_unique(array_map(static fn (string $f): string => explode(' ', $f)[0], $findings)));
        sort($contexts);

        return $contexts;
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string, string}
     */
    private function execute(array $args, string $cwd): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $code = (new ModuleBoundaryCommand($stdout, $stderr, $cwd))->run($args);

        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
