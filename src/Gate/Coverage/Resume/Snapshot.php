<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage\Resume;

/**
 * Content hashes of the project files a coverage run depends on, to tell exactly what changed since the kept run —
 * committed or not, amended or rebased: the comparison is by content, not by commit.
 *
 * The files are those of a list (`--files-from`, e.g. `git ls-files -co --exclude-standard` run on the host) or,
 * without one, every file under the project root; excluded paths are left out either way, and the `--input` paths
 * (dependency lock of the installed tree, local env files) always count.
 *
 * Exclude patterns: `dir/` excludes a directory prefix, anything else is an fnmatch() pattern matched against the
 * relative path and against the base name.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class Snapshot
{
    /**
     * @param list<string>|null $files    relative paths to consider (null: walk the root)
     * @param list<string>      $excludes
     * @param list<string>      $inputs   relative paths always hashed when present (files or directories)
     *
     * @return array<string, string> relative path => sha1 of the content, sorted by path
     */
    public static function take(string $root, ?array $files, array $excludes, array $inputs): array
    {
        $hashes = [];
        foreach ($files ?? self::walk($root, '', $excludes) as $path) {
            $path = self::normalise($path);
            if ('' !== $path && !self::isExcluded($path, $excludes) && is_file($root . '/' . $path)) {
                $hashes[$path] = (string) sha1_file($root . '/' . $path);
            }
        }
        foreach ($inputs as $input) {
            $input = self::normalise($input);
            $paths = is_dir($root . '/' . $input) ? self::walk($root, $input, []) : [$input];
            foreach ($paths as $path) {
                if (is_file($root . '/' . $path)) {
                    $hashes[$path] = (string) sha1_file($root . '/' . $path);
                }
            }
        }
        ksort($hashes, \SORT_STRING);

        return $hashes;
    }

    /**
     * @param array<string, string> $before
     * @param array<string, string> $after
     *
     * @return array{added: list<string>, deleted: list<string>, modified: list<string>}
     */
    public static function diff(array $before, array $after): array
    {
        $modified = [];
        foreach (array_intersect_key($after, $before) as $path => $hash) {
            if ($before[$path] !== $hash) {
                $modified[] = (string) $path;
            }
        }

        return [
            'added' => array_map(strval(...), array_keys(array_diff_key($after, $before))),
            'deleted' => array_map(strval(...), array_keys(array_diff_key($before, $after))),
            'modified' => $modified,
        ];
    }

    /**
     * @param list<string> $excludes
     */
    public static function isExcluded(string $path, array $excludes): bool
    {
        foreach ($excludes as $pattern) {
            if (str_ends_with($pattern, '/')) {
                if (str_starts_with($path . '/', $pattern)) {
                    return true;
                }
            } elseif (fnmatch($pattern, $path) || fnmatch($pattern, basename($path))) {
                return true;
            }
        }

        return false;
    }

    private static function normalise(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        return str_starts_with($path, './') ? substr($path, 2) : $path;
    }

    /**
     * @param list<string> $excludes
     *
     * @return list<string>
     */
    private static function walk(string $root, string $dir, array $excludes): array
    {
        $paths = [];
        $entries = scandir('' === $dir ? $root : $root . '/' . $dir) ?: [];
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = '' === $dir ? $entry : $dir . '/' . $entry;
            $full = $root . '/' . $path;
            if (is_dir($full) && !is_link($full)) {
                if (!self::isExcluded($path . '/', $excludes) && !self::isExcluded($path, $excludes)) {
                    array_push($paths, ...self::walk($root, $path, $excludes));
                }
            } elseif (!self::isExcluded($path, $excludes)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
