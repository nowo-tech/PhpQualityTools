<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Filesystem;

/**
 * Message of a directory walk that hit an unreadable directory. Up to PHP 8.5 the iterator's message names the
 * directory; PHP 8.6 dropped the path from it, so the first unreadable directory under the scanned paths is appended.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class UnreadableDirectory
{
    /**
     * @param string ...$bases The directories that were walked
     */
    public static function message(\UnexpectedValueException $e, string ...$bases): string
    {
        $path = null;
        foreach ($bases as $base) {
            $path ??= self::first($base);
        }
        $message = $e->getMessage();

        return null === $path || str_contains($message, $path) ? $message : $message . ' (' . $path . ')';
    }

    /**
     * The first unreadable directory under `$dir` (itself included), symbolic links not followed.
     */
    private static function first(string $dir): ?string
    {
        if (!is_dir($dir) || is_link($dir)) {
            return null;
        }
        if (!is_readable($dir)) {
            return $dir;
        }
        $found = null;
        foreach (scandir($dir) ?: [] as $entry) {
            if ('.' !== $entry && '..' !== $entry) {
                $found ??= self::first($dir . '/' . $entry);
            }
        }

        return $found;
    }
}
