<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ConfigException;
use NowoTech\PhpQualityTools\Gate\ModuleBoundary\ModuleBoundaryConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ModuleBoundaryConfigTest extends TestCase
{
    use TempTreeTrait;

    public function testBeaconYamlLoads(): void
    {
        $config = ModuleBoundaryConfig::fromFile(ModuleBoundaryCheckerTest::BEACON_CONFIG);

        self::assertCount(7, $config->modules);
        self::assertSame(['namespace' => 'App\\Shared', 'path' => 'src/Shared'], $config->modules['Shared']);
        self::assertCount(3, $config->forbiddenEdges);
        self::assertSame(['src/Shared/Listing/'], $config->forbiddenEdges[2]->except);
        self::assertCount(18, $config->rules);
        self::assertSame(['App\\Performance\\Entity\\'], \array_slice($config->rules[7]->imports, 3));
        self::assertSame(4, $config->rules[17]->withinLines);
        self::assertFalse($config->includeFullyQualified);
        self::assertSame(['src/Identity', 'src/Ingest', 'src/Issues', 'src/Ops', 'src/Performance', 'src/Project', 'src/Shared'], $config->paths);
    }

    public function testPhpConfigFile(): void
    {
        $root = $this->makeTree([
            'boundaries.php' => "<?php\nreturn ['paths' => ['./lib', 'src\\\\'], 'references' => 'all', 'modules' => ['A' => 'Acme\\\\A']];\n",
        ]);

        $config = ModuleBoundaryConfig::fromFile($root . '/boundaries.php');

        self::assertSame(['lib', 'src/'], $config->paths);
        self::assertTrue($config->includeFullyQualified);
        self::assertSame(['namespace' => 'Acme\\A', 'path' => null], $config->modules['A']);
    }

    /**
     * @param array<string, string> $files
     */
    #[DataProvider('badFileProvider')]
    public function testBadFiles(array $files, string $file, string $expected): void
    {
        $root = $this->makeTree($files);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage($expected);

        ModuleBoundaryConfig::fromFile($root . '/' . $file);
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function badFileProvider(): iterable
    {
        yield 'missing' => [[], 'nope.yaml', 'Module boundary config not found'];
        yield 'extension' => [['b.json' => '{}'], 'b.json', 'Unsupported config format'];
        yield 'yaml syntax' => [['b.yaml' => "modules: [unclosed\n"], 'b.yaml', 'Invalid YAML in'];
        yield 'yaml scalar' => [['b.yml' => "just text\n"], 'b.yml', 'must define a map (got string)'];
        yield 'php not array' => [['b.php' => "<?php\nreturn 42;\n"], 'b.php', 'must define a map (got int)'];
        yield 'invalid content prefixed with file' => [['b.yaml' => "colour: red\n"], 'b.yaml', 'b.yaml: Unknown key(s): colour'];
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('invalidProvider')]
    public function testInvalidArrays(array $data, string $expected): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage($expected);

        ModuleBoundaryConfig::fromArray($data);
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidProvider(): iterable
    {
        $modules = ['modules' => ['A' => ['namespace' => 'App\\A', 'path' => 'src/A']]];

        yield 'modules not a map' => [['modules' => 'x'], 'modules must be a map.'];
        yield 'modules list' => [['modules' => ['App\\A']], 'modules must be a map keyed by name.'];
        yield 'module without namespace' => [['modules' => ['A' => ['path' => 'src']]], 'modules.A needs a non-empty "namespace" string.'];
        yield 'module empty namespace' => [['modules' => ['A' => '\\']], 'modules.A needs a non-empty "namespace" string.'];
        yield 'module extra key' => [['modules' => ['A' => ['namespace' => 'A', 'dir' => 'x']]], 'modules.A: unknown key(s) dir'];
        yield 'module bad path' => [['modules' => ['A' => ['namespace' => 'A', 'path' => '']]], 'modules.A.path must be a non-empty string.'];
        yield 'allowed edges unknown source' => [[...$modules, 'allowed_edges' => ['B' => []]], 'allowed_edges references unknown module "B".'];
        yield 'allowed edges unknown target' => [[...$modules, 'allowed_edges' => ['A' => ['C']]], 'allowed_edges.A references unknown module "C".'];
        yield 'forbidden edges not a list' => [[...$modules, 'forbidden_edges' => 'x'], 'forbidden_edges must be a list.'];
        yield 'forbidden edge scalar' => [[...$modules, 'forbidden_edges' => ['x']], 'forbidden_edges[0] must be a map with from / to.'];
        yield 'forbidden edge extra key' => [[...$modules, 'forbidden_edges' => [['from' => 'A', 'to' => 'A', 'allow' => []]]], 'forbidden_edges[0]: unknown key(s) allow'];
        yield 'forbidden edge without to' => [[...$modules, 'forbidden_edges' => [['from' => 'A']]], 'forbidden_edges[0] needs "from" and "to".'];
        yield 'forbidden edge bad message' => [[...$modules, 'forbidden_edges' => [['from' => 'A', 'to' => 'A', 'message' => 1]]], 'forbidden_edges[0].message must be a string.'];
        yield 'forbidden edge empty name' => [[...$modules, 'forbidden_edges' => [['from' => [''], 'to' => 'A']]], 'forbidden_edges[0].from must contain non-empty strings.'];
        yield 'references' => [['references' => 'fqcn'], 'references must be "use" or "all".'];
        yield 'rule not a map' => [['rules' => ['x']], 'rules[0] must be a map with a "type".'];
        yield 'rule unknown type' => [['rules' => [['type' => 'forbid_everything']]], 'rules[0].type "forbid_everything" is unknown'];
        yield 'rule extra key' => [['rules' => [['type' => 'require_path', 'path' => 'a', 'in' => 'b']]], 'rules[0] (require_path): unknown key(s) in'];
        yield 'rule missing key' => [['rules' => [['type' => 'forbid_import']]], 'rules[0] (forbid_import) needs "imports".'];
        yield 'rule within_lines' => [['rules' => [['type' => 'require_near', 'path' => 'a', 'pattern' => 'a', 'requires' => 'b', 'within_lines' => -1]]], 'rules[0].within_lines must be a non-negative integer.'];
        yield 'rule bad regex' => [['rules' => [['type' => 'forbid_pattern', 'pattern' => '(unclosed']]], 'rules[0].pattern is not a valid regular expression: (unclosed'];
        yield 'rule pattern not string' => [['rules' => [['type' => 'forbid_pattern', 'pattern' => ['a']]]], 'rules[0].pattern must contain non-empty strings.'];
    }

    public function testCompilePatternEscapesDelimiter(): void
    {
        $regex = ModuleBoundaryConfig::compilePattern('a~b', 'x');

        self::assertSame('~a\~b~m', $regex);
        self::assertSame(1, preg_match($regex, 'a~b'));
    }
}
