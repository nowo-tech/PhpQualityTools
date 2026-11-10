<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * Ratchet file of accepted layer-rule findings: one `rule|file|subject` line per finding (a key repeated N times
 * accepts N findings), `#` comments. It may only shrink: a listed finding does not fail the run, a listed finding that
 * no longer occurs is reported (and fails with `--strict-baseline`), and `--update-baseline` refuses to add entries
 * to an existing file unless `--allow-grow` is given.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class LayerBaseline
{
    public const HEADER = "# check-module-boundaries layer baseline (php-quality-tools >= 1.12.0): accepted findings, one\n"
        . "# rule|file|subject line each. It may only shrink: regenerate with --update-baseline after fixing findings.\n";

    /**
     * @return array<string, int> key => accepted count
     */
    public static function load(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }
        $content = is_file($file) ? @file_get_contents($file) : false;
        if (false === $content) {
            throw new ConfigException("cannot read baseline {$file}");
        }
        $entries = [];
        foreach (explode("\n", $content) as $number => $line) {
            $line = trim($line);
            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }
            if (substr_count($line, '|') < 2) {
                throw new ConfigException(\sprintf('%s:%d is not a "rule|file|subject" baseline entry.', $file, $number + 1));
            }
            $entries[$line] = ($entries[$line] ?? 0) + 1;
        }

        return $entries;
    }

    /**
     * @param array<string, int>   $baseline
     * @param list<LayerViolation> $violations
     *
     * @return array{new: list<LayerViolation>, baselined: int, stale: list<string>}
     */
    public static function apply(array $baseline, array $violations): array
    {
        $new = [];
        $baselined = 0;
        foreach ($violations as $violation) {
            $key = $violation->key();
            if (($baseline[$key] ?? 0) > 0) {
                --$baseline[$key];
                ++$baselined;
                continue;
            }
            $new[] = $violation;
        }
        $stale = [];
        ksort($baseline);
        foreach ($baseline as $key => $count) {
            for ($i = 0; $i < $count; ++$i) {
                $stale[] = $key;
            }
        }

        return ['new' => $new, 'baselined' => $baselined, 'stale' => $stale];
    }

    /**
     * @param list<LayerViolation> $violations
     */
    public static function render(array $violations): string
    {
        $keys = array_map(static fn (LayerViolation $v): string => $v->key(), $violations);
        sort($keys);

        return self::HEADER . implode('', array_map(static fn (string $key): string => $key . "\n", $keys));
    }

    /**
     * Writes the baseline, only inside `$root` and never through a symbolic link.
     */
    public static function write(string $root, string $file, string $content): void
    {
        $realRoot = realpath($root);
        $directory = realpath(\dirname($file));
        if (false === $realRoot || false === $directory) {
            throw new ConfigException("baseline directory not found: {$file}");
        }
        if ($directory !== $realRoot && !str_starts_with($directory . '/', rtrim($realRoot, '/') . '/')) {
            throw new ConfigException("refusing to write the baseline outside the project root: {$file}");
        }
        if (is_link($file)) {
            throw new ConfigException("refusing to write the baseline through a symbolic link: {$file}");
        }
        if (false === @file_put_contents($file, $content)) {
            // A read-only file or directory: not reproducible in the suite, which runs as root in Docker.
            // @codeCoverageIgnoreStart
            throw new ConfigException("cannot write baseline {$file}");
            // @codeCoverageIgnoreEnd
        }
    }
}
