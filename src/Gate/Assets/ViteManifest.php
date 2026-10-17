<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Assets;

/**
 * Reads a Vite `manifest.json` (`build.manifest: true`) and lists the files a page loads for one entry:
 * the entry file plus its statically imported chunks (recursively) for JS, and their `css` for CSS.
 * Dynamic imports are not followed: lazy chunks load only where used.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ViteManifest
{
    /**
     * @param array<string, array{file: string, css?: list<string>, imports?: list<string>}> $chunks
     */
    public function __construct(
        private readonly array $chunks,
    ) {
    }

    public static function fromFile(string $file): self
    {
        if (!is_file($file)) {
            throw new AssetBudgetException("{$file} not found: run the production build first (e.g. pnpm run build / npm run build).", AssetBudgetException::MISSING_BUILD);
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!\is_array($data)) {
            throw new AssetBudgetException("{$file} is not a valid Vite manifest (JSON object expected).", AssetBudgetException::INVALID);
        }

        $chunks = [];
        foreach ($data as $key => $chunk) {
            if (!\is_array($chunk) || !isset($chunk['file']) || !\is_string($chunk['file'])
                || !self::isStringList($chunk['css'] ?? []) || !self::isStringList($chunk['imports'] ?? [])) {
                throw new AssetBudgetException("{$file}: chunk \"{$key}\" needs a \"file\" string and optional \"css\" / \"imports\" string lists.", AssetBudgetException::INVALID);
            }
            /* @var array{file: string, css?: list<string>, imports?: list<string>} $chunk */
            $chunks[(string) $key] = $chunk;
        }

        return new self($chunks);
    }

    public function has(string $entry): bool
    {
        return isset($this->chunks[$entry]);
    }

    /**
     * @return array{js: list<string>, css: list<string>} files relative to the build directory, without duplicates
     */
    public function files(string $entry): array
    {
        $js = [];
        $css = [];
        $seen = [];
        $this->collect($entry, $js, $css, $seen);

        return ['js' => array_values(array_unique($js)), 'css' => array_values(array_unique($css))];
    }

    /**
     * @param list<string>        $js
     * @param list<string>        $css
     * @param array<string, true> $seen
     *
     * @param-out list<string> $js
     * @param-out list<string> $css
     * @param-out array<string, true> $seen
     */
    private function collect(string $key, array &$js, array &$css, array &$seen): void
    {
        if (isset($seen[$key])) {
            return;
        }
        if (!isset($this->chunks[$key])) {
            throw new AssetBudgetException("Vite manifest: chunk \"{$key}\" is imported but not defined.", AssetBudgetException::INVALID);
        }
        $seen[$key] = true;
        $chunk = $this->chunks[$key];
        if (str_ends_with($chunk['file'], '.css')) {
            $css[] = $chunk['file'];
        } elseif (str_ends_with($chunk['file'], '.js') || str_ends_with($chunk['file'], '.mjs')) {
            $js[] = $chunk['file'];
        }
        foreach ($chunk['css'] ?? [] as $file) {
            $css[] = $file;
        }
        foreach ($chunk['imports'] ?? [] as $imported) {
            $this->collect($imported, $js, $css, $seen);
        }
    }

    private static function isStringList(mixed $value): bool
    {
        return \is_array($value) && array_is_list($value) && $value === array_filter($value, is_string(...));
    }
}
