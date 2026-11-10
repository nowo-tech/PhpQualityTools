<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * Ratchet file of accepted layer-rule findings: one `rule|file|subject` line per finding (a key repeated N times
 * accepts N findings), `#` comments. It may only shrink: a listed finding does not fail the run, a listed finding that
 * no longer occurs is reported (and fails with `--strict-baseline`), and `--update-baseline` on an existing file keeps
 * only the listed findings that still occur (new findings are added only with `--allow-grow`).
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
     * @return array{new: list<LayerViolation>, baselined: int, matched: list<LayerViolation>, stale: list<string>}
     */
    public static function apply(array $baseline, array $violations): array
    {
        $new = [];
        $matched = [];
        foreach ($violations as $violation) {
            $key = $violation->key();
            if (($baseline[$key] ?? 0) > 0) {
                --$baseline[$key];
                $matched[] = $violation;
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

        return ['new' => $new, 'baselined' => \count($matched), 'matched' => $matched, 'stale' => $stale];
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
     * Writes the baseline atomically (temporary file in the same directory, then rename), only inside `$root` and never
     * through a symbolic link.
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
        // Check and write the same, canonical path (no gap between the resolved directory and the given path).
        $target = $directory . '/' . basename($file);
        if (is_link($target)) {
            throw new ConfigException("refusing to write the baseline through a symbolic link: {$file}");
        }
        $mode = is_file($target) ? fileperms($target) & 0o777 : 0o666 & ~umask();
        // `x` = O_CREAT | O_EXCL: never opens an existing file or follows a planted link; rename() replaces a link
        // instead of following it, and readers see the old or the new file, never a truncated one.
        $temp = $directory . '/.' . basename($file) . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $handle = @fopen($temp, 'x');
        $written = false !== $handle && false !== fwrite($handle, $content) && fclose($handle) && @chmod($temp, $mode) && @rename($temp, $target);
        if (!$written) {
            // A read-only directory: not reproducible in the suite, which runs as root in Docker.
            // @codeCoverageIgnoreStart
            @unlink($temp);

            throw new ConfigException("cannot write baseline {$file}");
            // @codeCoverageIgnoreEnd
        }
    }
}
