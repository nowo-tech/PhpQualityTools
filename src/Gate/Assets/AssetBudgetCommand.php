<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Assets;

/**
 * `bin/check-asset-budgets`: fail when a Vite entry (plus its statically imported chunks) grows past its size budget.
 *
 * Usage: check-asset-budgets [--root=<dir>] [--config=<file>] [--manifest=<file>] [--build-dir=<dir>]
 *        [--budget=<entry>:<kind>=<size>]...
 *
 * Budgets come from `config/quality/asset-budgets.{yaml,yml,json,php}` (or `--config`) and/or `--budget`
 * (which overrides the config for the same entry and kind). See AssetBudgetConfig for the format.
 *
 * Exit codes: 0 OK, 1 over budget / entry missing from the manifest / build output missing,
 * 2 usage, configuration or manifest format error.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class AssetBudgetCommand
{
    public const DEFAULT_CONFIGS = [
        'config/quality/asset-budgets.yaml',
        'config/quality/asset-budgets.yml',
        'config/quality/asset-budgets.json',
        'config/quality/asset-budgets.php',
    ];

    public const DEFAULT_MANIFESTS = [
        'public/build/.vite/manifest.json',
        'public/build/manifest.json',
    ];

    public const USAGE = <<<'TXT'
        Usage: check-asset-budgets [--root=<dir>] [--config=<file>] [--manifest=<file>] [--build-dir=<dir>]
                                   [--budget=<entry>:<kind>=<size>]...

        Fails when a Vite entry (entry file + statically imported chunks; lazy chunks are not counted)
        is larger than its budget. Run the production build first.
          --root=<dir>        project root; other paths are relative to it (default: current directory)
          --config=<file>     YAML (needs symfony/yaml), JSON or PHP budgets file
                              (default: config/quality/asset-budgets.{yaml,yml,json,php} when present)
          --manifest=<file>   Vite manifest (default: public/build/.vite/manifest.json, then public/build/manifest.json)
          --build-dir=<dir>   directory the manifest paths are relative to (default: manifest dir without .vite/)
          --budget=<entry>:<kind>=<size>
                              e.g. --budget=assets/app.ts:css=250kB; kinds js, css, js_gzip, css_gzip;
                              sizes in bytes or with B, kB (1000), KiB (1024), MB, MiB; repeatable
        Exit codes: 0 OK, 1 over budget / entry or build output missing, 2 usage or configuration error.
        TXT;

    /** @var \Closure(string): ?string */
    private readonly \Closure $gzip;

    /**
     * @param resource                         $stdout
     * @param resource                         $stderr
     * @param (callable(string): ?string)|null $gzip   gzip -9 compressor (default: gzencode when ext-zlib is loaded)
     */
    public function __construct(
        private $stdout,
        private $stderr,
        private readonly string $cwd,
        ?callable $gzip = null,
    ) {
        $this->gzip = null !== $gzip ? $gzip(...) : static fn (string $data): ?string => \function_exists('gzencode') ? (gzencode($data, 9) ?: null) : null;
    }

    /**
     * @param list<string> $args Arguments without the script name
     */
    public function run(array $args): int
    {
        $root = $this->cwd;
        $configFile = null;
        $manifestFile = null;
        $buildDir = null;
        $cliBudgets = [];
        foreach ($args as $arg) {
            if ('-h' === $arg || '--help' === $arg) {
                fwrite($this->stdout, self::USAGE . "\n");

                return 0;
            }
            [$name, $value] = str_contains($arg, '=') ? explode('=', $arg, 2) : [$arg, ''];
            if ('' === $value) {
                return $this->usageError("Unknown or empty option {$arg}");
            }
            if ('--root' === $name) {
                $root = $this->resolve($value, $this->cwd);
            } elseif ('--config' === $name) {
                $configFile = $value;
            } elseif ('--manifest' === $name) {
                $manifestFile = $value;
            } elseif ('--build-dir' === $name) {
                $buildDir = $value;
            } elseif ('--budget' === $name) {
                $cliBudgets[] = $value;
            } else {
                return $this->usageError("Unknown option {$arg}");
            }
        }
        if (!is_dir($root)) {
            fwrite($this->stderr, "ERROR: root directory not found: {$root}\n");

            return 2;
        }

        try {
            $config = $this->loadConfig($root, $configFile, $cliBudgets);
            if ([] === $config->budgets) {
                return $this->usageError('No budget: pass --budget=<entry>:<kind>=<size> or create config/quality/asset-budgets.yaml.');
            }
            $manifestPath = $this->manifestPath($root, $manifestFile ?? $config->manifest);
            $buildDir ??= $config->buildDir;
            $buildPath = null !== $buildDir
                ? $this->resolve($buildDir, $root)
                : (string) preg_replace('#/\.vite$#', '', \dirname($manifestPath));
            $manifest = ViteManifest::fromFile($manifestPath);

            return $this->check($config, $manifest, $buildPath);
        } catch (AssetBudgetException $e) {
            fwrite($this->stderr, 'ERROR: ' . $e->getMessage() . "\n");

            return AssetBudgetException::MISSING_BUILD === $e->getCode() ? 1 : 2;
        }
    }

    private function check(AssetBudgetConfig $config, ViteManifest $manifest, string $buildPath): int
    {
        $checks = 0;
        $failed = 0;
        foreach ($config->budgets as $entry => $budgets) {
            if (!$manifest->has($entry)) {
                fwrite($this->stdout, "FAIL {$entry}: not in the manifest (renamed entry? update the budgets).\n");
                ++$failed;
                continue;
            }
            $files = $manifest->files($entry);
            foreach (AssetBudgetConfig::KINDS as $kind) {
                if (!isset($budgets[$kind])) {
                    continue;
                }
                $type = str_starts_with($kind, 'js') ? 'js' : 'css';
                [$raw, $gzip] = $this->size($buildPath, $files[$type]);
                $gzipBudget = str_ends_with($kind, '_gzip');
                if ($gzipBudget && null === $gzip) {
                    throw new AssetBudgetException("{$entry} {$kind}: gzip budgets need ext-zlib.", AssetBudgetException::INVALID);
                }
                $measured = $gzipBudget ? (int) $gzip : $raw;
                $over = $measured > $budgets[$kind];
                ++$checks;
                $failed += $over ? 1 : 0;
                fwrite($this->stdout, \sprintf(
                    "%s %s %s: %s / budget %s\n",
                    $over ? 'FAIL' : 'OK  ',
                    $entry,
                    $kind,
                    $gzipBudget ? 'gzip ' . $this->kb((int) $gzip) . ' (raw ' . $this->kb($raw) . ')' : $this->kb($raw) . ' (gzip ' . (null === $gzip ? 'n/a' : $this->kb($gzip)) . ')',
                    $this->kb($budgets[$kind]),
                ));
            }
        }

        if ($failed > 0) {
            fwrite($this->stderr, "ERROR: asset budgets: {$failed} check(s) failed.\n");

            return 1;
        }
        fwrite($this->stdout, \sprintf("OK: asset budgets (%d check(s), %d entr%s).\n", $checks, \count($config->budgets), 1 === \count($config->budgets) ? 'y' : 'ies'));

        return 0;
    }

    /**
     * @param list<string> $cliBudgets
     */
    private function loadConfig(string $root, ?string $configFile, array $cliBudgets): AssetBudgetConfig
    {
        if (null !== $configFile) {
            $config = AssetBudgetConfig::fromFile($this->resolve($configFile, $root));
        } else {
            $config = new AssetBudgetConfig();
            foreach (self::DEFAULT_CONFIGS as $candidate) {
                if (is_file($root . '/' . $candidate)) {
                    $config = AssetBudgetConfig::fromFile($root . '/' . $candidate);
                    break;
                }
            }
        }
        foreach ($cliBudgets as $budget) {
            if (1 !== preg_match('/^(.+):([a-z_]+)=(.+)$/', $budget, $m)) {
                throw new AssetBudgetException("--budget must look like <entry>:<kind>=<size> (got {$budget}).", AssetBudgetException::INVALID);
            }
            $config = $config->with($m[1], $m[2], $m[3]);
        }

        return $config;
    }

    private function manifestPath(string $root, ?string $manifest): string
    {
        if (null !== $manifest) {
            return $this->resolve($manifest, $root);
        }
        foreach (self::DEFAULT_MANIFESTS as $candidate) {
            if (is_file($root . '/' . $candidate)) {
                return $root . '/' . $candidate;
            }
        }

        return $root . '/' . self::DEFAULT_MANIFESTS[0];
    }

    /**
     * @param list<string> $files
     *
     * @return array{int, ?int} raw bytes, gzip bytes (null without ext-zlib)
     */
    private function size(string $buildPath, array $files): array
    {
        $raw = 0;
        $gzip = 0;
        foreach ($files as $file) {
            $path = $buildPath . '/' . $file;
            if (!is_file($path)) {
                throw new AssetBudgetException("{$path} is listed in the manifest but missing: rebuild the assets.", AssetBudgetException::MISSING_BUILD);
            }
            $data = (string) file_get_contents($path);
            $raw += \strlen($data);
            $compressed = ($this->gzip)($data);
            $gzip = null === $compressed || null === $gzip ? null : $gzip + \strlen($compressed);
        }

        return [$raw, $gzip];
    }

    private function kb(int $bytes): string
    {
        return \sprintf('%.1f kB', $bytes / 1000);
    }

    private function resolve(string $path, string $base): string
    {
        return rtrim(str_starts_with($path, '/') ? $path : $base . '/' . $path, '/');
    }

    private function usageError(string $message): int
    {
        fwrite($this->stderr, "ERROR: {$message}\n" . self::USAGE . "\n");

        return 2;
    }
}
