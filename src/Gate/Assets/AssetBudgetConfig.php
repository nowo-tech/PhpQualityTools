<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Assets;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Per-entry size budgets for a Vite build, loaded from YAML (needs `symfony/yaml`), JSON or a PHP file returning an array.
 *
 * ```yaml
 * manifest: public/build/.vite/manifest.json   # optional (default: public/build/.vite/manifest.json, then public/build/manifest.json)
 * build_dir: public/build                       # optional (default: the manifest directory without .vite/)
 * budgets:                                      # manifest entry => kind => size
 *   assets/app.ts: { js: 200kB, css: 250kB }
 *   assets/styles/shell-entry.scss: { css: 60kB, css_gzip: 15kB }
 * ```
 *
 * Kinds: `js`, `css` (raw bytes) and `js_gzip`, `css_gzip` (gzip -9 bytes). Sizes: integer bytes or a
 * number with a unit: `B`, `k` / `kB` (1000), `KiB` (1024), `M` / `MB` (1000²), `MiB` (1024²), case-insensitive.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class AssetBudgetConfig
{
    public const KINDS = ['js', 'css', 'js_gzip', 'css_gzip'];

    private const TOP_LEVEL_KEYS = ['manifest', 'build_dir', 'budgets'];

    private const UNITS = ['' => 1, 'b' => 1, 'k' => 1000, 'kb' => 1000, 'kib' => 1024, 'm' => 1000000, 'mb' => 1000000, 'mib' => 1048576];

    /**
     * @param array<string, array<string, int>> $budgets entry => kind => bytes
     */
    public function __construct(
        public readonly array $budgets = [],
        public readonly ?string $manifest = null,
        public readonly ?string $buildDir = null,
    ) {
    }

    public static function fromFile(string $file): self
    {
        if (!is_file($file)) {
            throw new AssetBudgetException("Asset budget config not found: {$file}", AssetBudgetException::INVALID);
        }

        $extension = strtolower(pathinfo($file, \PATHINFO_EXTENSION));
        if ('php' === $extension) {
            $data = (static fn (string $path): mixed => require $path)($file);
        } elseif ('json' === $extension) {
            $data = json_decode((string) file_get_contents($file), true);
            if (\JSON_ERROR_NONE !== json_last_error()) {
                throw new AssetBudgetException("Invalid JSON in {$file}: " . json_last_error_msg(), AssetBudgetException::INVALID);
            }
        } elseif ('yaml' === $extension || 'yml' === $extension) {
            if (!class_exists(Yaml::class)) {
                // symfony/yaml is a dev dependency of this package, so this branch never runs here.
                // @codeCoverageIgnoreStart
                throw new AssetBudgetException('Reading a YAML config needs symfony/yaml (composer require --dev symfony/yaml), or use a JSON / PHP file.', AssetBudgetException::INVALID);
                // @codeCoverageIgnoreEnd
            }

            try {
                $data = Yaml::parseFile($file);
            } catch (ParseException $e) {
                throw new AssetBudgetException("Invalid YAML in {$file}: " . $e->getMessage(), AssetBudgetException::INVALID, $e);
            }
        } else {
            throw new AssetBudgetException("Unsupported config format for {$file} (use .yaml, .yml, .json or .php).", AssetBudgetException::INVALID);
        }

        if (!\is_array($data)) {
            throw new AssetBudgetException("{$file} must define a map (got " . get_debug_type($data) . ').', AssetBudgetException::INVALID);
        }

        try {
            return self::fromArray($data);
        } catch (AssetBudgetException $e) {
            throw new AssetBudgetException("{$file}: " . $e->getMessage(), AssetBudgetException::INVALID, $e);
        }
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $unknown = array_diff(array_keys($data), self::TOP_LEVEL_KEYS);
        if ([] !== $unknown) {
            throw new AssetBudgetException('Unknown key(s): ' . implode(', ', $unknown) . ' (allowed: ' . implode(', ', self::TOP_LEVEL_KEYS) . ').', AssetBudgetException::INVALID);
        }
        foreach (['manifest', 'build_dir'] as $key) {
            if (isset($data[$key]) && (!\is_string($data[$key]) || '' === $data[$key])) {
                throw new AssetBudgetException("{$key} must be a non-empty string.", AssetBudgetException::INVALID);
            }
        }
        $budgets = $data['budgets'] ?? [];
        if (!\is_array($budgets)) {
            throw new AssetBudgetException('budgets must be a map of entry => { kind: size }.', AssetBudgetException::INVALID);
        }

        $config = new self([], $data['manifest'] ?? null, $data['build_dir'] ?? null);
        foreach ($budgets as $entry => $kinds) {
            if (!\is_array($kinds) || [] === $kinds) {
                throw new AssetBudgetException("budgets.{$entry} must be a non-empty map of kind => size.", AssetBudgetException::INVALID);
            }
            foreach ($kinds as $kind => $size) {
                $config = $config->with((string) $entry, (string) $kind, $size);
            }
        }

        return $config;
    }

    /**
     * Adds (or replaces) one budget, e.g. from `--budget=assets/app.ts:css=250kB`.
     */
    public function with(string $entry, string $kind, mixed $size): self
    {
        if ('' === $entry) {
            throw new AssetBudgetException('Budget entry must not be empty.', AssetBudgetException::INVALID);
        }
        if (!\in_array($kind, self::KINDS, true)) {
            throw new AssetBudgetException("Unknown budget kind \"{$kind}\" for {$entry} (allowed: " . implode(', ', self::KINDS) . ').', AssetBudgetException::INVALID);
        }
        $budgets = $this->budgets;
        $budgets[$entry][$kind] = self::parseSize($size, "{$entry} {$kind}");

        return new self($budgets, $this->manifest, $this->buildDir);
    }

    public static function parseSize(mixed $size, string $context = 'size'): int
    {
        if (\is_int($size) && $size > 0) {
            return $size;
        }
        if (\is_string($size) && 1 === preg_match('/^\s*(\d+(?:\.\d+)?)\s*([a-z]*)\s*$/i', $size, $m)) {
            $unit = strtolower($m[2]);
            $bytes = isset(self::UNITS[$unit]) ? (int) round((float) $m[1] * self::UNITS[$unit]) : 0;
            if ($bytes > 0) {
                return $bytes;
            }
        }

        throw new AssetBudgetException("Invalid size for {$context}: " . (\is_scalar($size) ? var_export($size, true) : get_debug_type($size)) . ' (use bytes or a number with B, kB, KiB, MB or MiB).', AssetBudgetException::INVALID);
    }
}
