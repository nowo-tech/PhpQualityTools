<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Twig;

/**
 * Finds the Twig files of a bundle checkout for the Twig gates.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class TemplateFiles
{
    /** Directories never entered. */
    private const SKIPPED = ['vendor', 'node_modules', 'var', '.git'];

    /**
     * @throws \RuntimeException when a directory cannot be read ("cannot read ...")
     *
     * @return list<string> Paths relative to `$root`, sorted, of every `*.twig` file under `$dir` (empty when the directory does not exist)
     */
    public static function in(string $root, string $dir): array
    {
        $base = $root . '/' . $dir;
        if (!is_dir($base)) {
            return [];
        }
        $files = [];

        try {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                static fn (\SplFileInfo $file): bool => !($file->isDir() && (\in_array($file->getFilename(), self::SKIPPED, true) || str_starts_with($file->getFilename(), '.'))),
            ));
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.twig')) {
                    $files[] = substr($file->getPathname(), \strlen($root) + 1);
                }
            }
        } catch (\UnexpectedValueException $e) {
            throw new \RuntimeException('cannot read a directory: ' . $e->getMessage(), 0, $e);
        }
        sort($files);

        return $files;
    }

    /**
     * The `templates` directories of the demos: `demo/<name>/templates` and `demo/<a>/<b>/templates`. Only these two
     * depths are looked at (no walk through the rest of `demo/`, whose git-ignored data folders may be unreadable).
     *
     * @return list<string> Paths relative to `$root`, sorted
     */
    public static function demoTemplateDirectories(string $root): array
    {
        $dirs = [];
        foreach (['/demo/*/templates', '/demo/*/*/templates'] as $pattern) {
            foreach (glob($root . $pattern, \GLOB_ONLYDIR) ?: [] as $dir) {
                $relative = substr($dir, \strlen($root) + 1);
                if ([] === array_intersect(explode('/', $relative), self::SKIPPED)) {
                    $dirs[] = $relative;
                }
            }
        }
        sort($dirs);

        return $dirs;
    }
}
