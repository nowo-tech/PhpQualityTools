<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

use NowoTech\PhpQualityTools\Gate\Assets\AssetBudgetCommand;
use NowoTech\PhpQualityTools\Gate\Assets\AssetBudgetConfig;
use NowoTech\PhpQualityTools\Gate\Assets\AssetBudgetException;
use NowoTech\PhpQualityTools\Gate\Assets\ViteManifest;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class AssetBudgetCommandTest extends TestCase
{
    use TempTreeTrait;

    private const MANIFEST = [
        'assets/app.ts' => ['file' => 'assets/app-1.js', 'css' => ['assets/app-1.css'], 'imports' => ['_vendor.js', '_shared.js'], 'dynamicImports' => ['assets/lazy.ts']],
        '_vendor.js' => ['file' => 'assets/vendor-2.js', 'imports' => ['_shared.js']],
        '_shared.js' => ['file' => 'assets/shared-3.js', 'css' => ['assets/shared-3.css'], 'imports' => ['assets/app.ts']],
        'assets/lazy.ts' => ['file' => 'assets/lazy-4.js'],
        'assets/styles/shell.scss' => ['file' => 'assets/shell-5.css', 'src' => 'assets/styles/shell.scss', 'isEntry' => true],
        'assets/font.woff2' => ['file' => 'assets/font-6.woff2'],
    ];

    public function testWithinBudgetFromYamlConfig(): void
    {
        $root = $this->makeBuild([
            'config/quality/asset-budgets.yaml' => "budgets:\n  assets/app.ts: { js: 2kB, css: 1KiB, js_gzip: 1k }\n  assets/styles/shell.scss: { css: 500 }\n",
        ]);

        [$code, $out, $err] = $this->runCommand($root, []);

        self::assertSame(0, $code, $err);
        self::assertSame(
            "OK   assets/app.ts js: 0.6 kB (gzip 0.1 kB) / budget 2.0 kB\n"
            . "OK   assets/app.ts css: 0.3 kB (gzip 0.0 kB) / budget 1.0 kB\n"
            . "OK   assets/app.ts js_gzip: gzip 0.1 kB (raw 0.6 kB) / budget 1.0 kB\n"
            . "OK   assets/styles/shell.scss css: 0.4 kB (gzip 0.0 kB) / budget 0.5 kB\n"
            . "OK: asset budgets (4 check(s), 2 entries).\n",
            $out,
        );
    }

    public function testOverBudgetAndMissingEntryFail(): void
    {
        $root = $this->makeBuild([]);

        [$code, $out, $err] = $this->runCommand($root, [
            '--budget=assets/app.ts:js=500',
            '--budget=assets/app.ts:css_gzip=1B',
            '--budget=assets/old.ts:js=1MB',
        ]);

        self::assertSame(1, $code);
        self::assertStringContainsString("FAIL assets/app.ts js: 0.6 kB (gzip 0.1 kB) / budget 0.5 kB\n", $out);
        self::assertStringContainsString('FAIL assets/app.ts css_gzip: gzip ', $out);
        self::assertStringContainsString("FAIL assets/old.ts: not in the manifest (renamed entry? update the budgets).\n", $out);
        self::assertSame("ERROR: asset budgets: 3 check(s) failed.\n", $err);
    }

    public function testCliBudgetOverridesConfigAndCustomPaths(): void
    {
        $root = $this->makeTree([
            'web/dist/manifest.json' => json_encode(['main.js' => ['file' => 'main-1.js']], \JSON_THROW_ON_ERROR),
            'web/dist/main-1.js' => str_repeat('x', 1500),
            'budgets.json' => json_encode(['manifest' => 'web/dist/manifest.json', 'budgets' => ['main.js' => ['js' => '1kB']]], \JSON_THROW_ON_ERROR),
            'budgets.php' => "<?php return ['budgets' => ['main.js' => ['js' => 100]]];",
        ]);

        [$code] = $this->runCommand($root, ['--config=budgets.json']);
        self::assertSame(1, $code);

        [$code, $out] = $this->runCommand($root, ['--config=budgets.json', '--budget=main.js:js=1.5kB']);
        self::assertSame(0, $code);
        self::assertSame("OK   main.js js: 1.5 kB (gzip 0.0 kB) / budget 1.5 kB\nOK: asset budgets (1 check(s), 1 entry).\n", $out);

        [$code, $out] = $this->runCommand(\dirname($root), ['--root=' . basename($root), '--config=' . $root . '/budgets.php', '--manifest=web/dist/manifest.json', '--build-dir=web/dist/', '--budget=main.js:js=2k']);
        self::assertSame(0, $code, $out);

        // build_dir from the config file.
        file_put_contents($root . '/web/dist/budgets.yml', "manifest: web/dist/manifest.json\nbuild_dir: web/dist\nbudgets: { main.js: { js: 2kB } }\n");
        [$code] = $this->runCommand($root, ['--config=web/dist/budgets.yml']);
        self::assertSame(0, $code);
    }

    public function testDefaultManifestLocationsAndMissingBuild(): void
    {
        $root = $this->makeTree([
            'public/build/manifest.json' => json_encode(['a.js' => ['file' => 'a.js']], \JSON_THROW_ON_ERROR),
            'public/build/a.js' => 'a',
        ]);
        [$code] = $this->runCommand($root, ['--budget=a.js:js=1']);
        self::assertSame(0, $code);

        $empty = $this->makeTree(['README.md' => '']);
        [$code, , $err] = $this->runCommand($empty, ['--budget=a.js:js=1']);
        self::assertSame(1, $code);
        self::assertSame("ERROR: {$empty}/public/build/.vite/manifest.json not found: run the production build first (e.g. pnpm run build / npm run build).\n", $err);

        unlink($root . '/public/build/a.js');
        [$code, , $err] = $this->runCommand($root, ['--budget=a.js:js=1']);
        self::assertSame(1, $code);
        self::assertStringContainsString('public/build/a.js is listed in the manifest but missing: rebuild the assets.', $err);
    }

    public function testWithoutZlibGzipIsNotAvailable(): void
    {
        $root = $this->makeBuild([]);

        [$code, $out] = $this->runCommand($root, ['--budget=assets/app.ts:js=1MB'], static fn (string $data): ?string => null);
        self::assertSame(0, $code);
        self::assertStringContainsString('(gzip n/a)', $out);

        [$code, , $err] = $this->runCommand($root, ['--budget=assets/app.ts:js_gzip=1MB'], static fn (string $data): ?string => null);
        self::assertSame(2, $code);
        self::assertSame("ERROR: assets/app.ts js_gzip: gzip budgets need ext-zlib.\n", $err);
    }

    public function testHelpAndUsageErrors(): void
    {
        $root = $this->makeBuild([]);

        [$code, $out] = $this->runCommand($root, ['-h']);
        self::assertSame(0, $code);
        self::assertStringContainsString('Usage: check-asset-budgets', $out);

        foreach ([
            [[], 'No budget: pass --budget'],
            [['--budget'], 'Unknown or empty option --budget'],
            [['--nope=1'], 'Unknown option --nope=1'],
            [['--budget=assets/app.ts=1'], '--budget must look like <entry>:<kind>=<size> (got assets/app.ts=1).'],
            [['--budget=assets/app.ts:wasm=1'], 'Unknown budget kind "wasm" for assets/app.ts'],
            [['--budget=assets/app.ts:js=0'], "Invalid size for assets/app.ts js: '0'"],
            [['--budget=assets/app.ts:js=1GB'], "Invalid size for assets/app.ts js: '1GB'"],
            [['--config=missing.yaml'], 'Asset budget config not found: ' . $root . '/missing.yaml'],
            [['--root=nowhere'], 'root directory not found: ' . $root . '/nowhere'],
        ] as [$args, $message]) {
            [$code, , $err] = $this->runCommand($root, $args);
            self::assertSame(2, $code, implode(' ', $args));
            self::assertStringContainsString($message, $err, implode(' ', $args));
        }
    }

    public function testInvalidManifests(): void
    {
        foreach ([
            '[1' => 'is not a valid Vite manifest',
            '{"a.js": {"css": []}}' => 'chunk "a.js" needs a "file" string',
            '{"a.js": {"file": "a.js", "imports": ["b.js"]}}' => 'chunk "b.js" is imported but not defined.',
            '{"a.js": {"file": "a.js", "css": {"x": "y"}}}' => 'chunk "a.js" needs',
            '{"a.js": {"file": "a.js", "imports": [1]}}' => 'chunk "a.js" needs',
            '{"a.js": "a.js"}' => 'chunk "a.js" needs',
        ] as $json => $message) {
            $root = $this->makeTree(['public/build/.vite/manifest.json' => $json, 'public/build/a.js' => 'a']);
            [$code, , $err] = $this->runCommand($root, ['--budget=a.js:js=1kB']);
            self::assertSame(2, $code, $json);
            self::assertStringContainsString($message, $err, $json);
        }
    }

    public function testConfigFiles(): void
    {
        $root = $this->makeTree([
            'bad.yaml' => "budgets: [\n",
            'bad.json' => '{',
            'list.yaml' => '"just a string"',
            'unknown.yaml' => "budget: {}\n",
            'kinds.yaml' => "budgets: { a.js: [] }\n",
            'budgets-list.json' => '{"budgets": 3}',
            'manifest.json' => '{"manifest": ""}',
            'budgets.ini' => '',
        ]);

        foreach ([
            'bad.yaml' => 'Invalid YAML in',
            'bad.json' => 'Invalid JSON in',
            'list.yaml' => 'must define a map (got string)',
            'unknown.yaml' => 'unknown.yaml: Unknown key(s): budget (allowed: manifest, build_dir, budgets).',
            'kinds.yaml' => 'budgets.a.js must be a non-empty map of kind => size.',
            'budgets-list.json' => 'budgets must be a map of entry => { kind: size }.',
            'manifest.json' => 'manifest must be a non-empty string.',
            'budgets.ini' => 'Unsupported config format',
        ] as $file => $message) {
            try {
                AssetBudgetConfig::fromFile($root . '/' . $file);
                self::fail($file);
            } catch (AssetBudgetException $e) {
                self::assertStringContainsString($message, $e->getMessage(), $file);
                self::assertSame(AssetBudgetException::INVALID, $e->getCode());
            }
        }
    }

    public function testConfigApi(): void
    {
        self::assertSame(1, AssetBudgetConfig::parseSize(1));
        self::assertSame(250000, AssetBudgetConfig::parseSize('250 kB'));
        self::assertSame(1536, AssetBudgetConfig::parseSize('1.5KiB'));
        self::assertSame(2000000, AssetBudgetConfig::parseSize('2M'));
        self::assertSame(1048576, AssetBudgetConfig::parseSize('1MiB'));

        $config = (new AssetBudgetConfig())->with('a.js', 'js', '1kB')->with('a.js', 'js', 2);
        self::assertSame(['a.js' => ['js' => 2]], $config->budgets);

        $this->expectException(AssetBudgetException::class);
        $this->expectExceptionMessage('Invalid size for size: array');
        AssetBudgetConfig::parseSize([]);
    }

    public function testEmptyEntryIsRejected(): void
    {
        $this->expectExceptionMessage('Budget entry must not be empty.');
        (new AssetBudgetConfig())->with('', 'js', 1);
    }

    public function testManifestCollectsStaticImportsOnce(): void
    {
        $root = $this->makeTree(['m.json' => json_encode(self::MANIFEST, \JSON_THROW_ON_ERROR)]);
        $manifest = ViteManifest::fromFile($root . '/m.json');

        self::assertTrue($manifest->has('assets/app.ts'));
        self::assertFalse($manifest->has('assets/missing.ts'));
        self::assertSame([
            'js' => ['assets/app-1.js', 'assets/vendor-2.js', 'assets/shared-3.js'],
            'css' => ['assets/app-1.css', 'assets/shared-3.css'],
        ], $manifest->files('assets/app.ts'));
        self::assertSame(['js' => [], 'css' => ['assets/shell-5.css']], $manifest->files('assets/styles/shell.scss'));
        self::assertSame(['js' => [], 'css' => []], $manifest->files('assets/font.woff2'));
    }

    /**
     * @param array<string, string> $extra
     */
    private function makeBuild(array $extra): string
    {
        return $this->makeTree([
            'public/build/.vite/manifest.json' => json_encode(self::MANIFEST, \JSON_THROW_ON_ERROR),
            'public/build/assets/app-1.js' => str_repeat('a', 300),
            'public/build/assets/vendor-2.js' => str_repeat('v', 200),
            'public/build/assets/shared-3.js' => str_repeat('s', 100) . '.mjs',
            'public/build/assets/lazy-4.js' => str_repeat('l', 100000),
            'public/build/assets/app-1.css' => str_repeat('c', 250),
            'public/build/assets/shared-3.css' => str_repeat('d', 50),
            'public/build/assets/shell-5.css' => str_repeat('e', 400),
            ...$extra,
        ]);
    }

    /**
     * @param list<string>                     $args
     * @param (callable(string): ?string)|null $gzip
     *
     * @return array{int, string, string}
     */
    private function runCommand(string $cwd, array $args, ?callable $gzip = null): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);
        $code = (new AssetBudgetCommand($stdout, $stderr, $cwd, $gzip))->run($args);
        rewind($stdout);
        rewind($stderr);

        return [$code, (string) stream_get_contents($stdout), (string) stream_get_contents($stderr)];
    }
}
