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
     * @return list<string> Paths relative to `$root`, sorted, of every `*.twig` file under `$dir` (empty when the directory does not exist)
     */
    public static function in(string $root, string $dir): array
    {
        $base = $root . '/' . $dir;
        if (!is_dir($base)) {
            return [];
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            static fn (\SplFileInfo $file): bool => !$file->isDir() || !\in_array($file->getFilename(), self::SKIPPED, true),
        ));
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.twig')) {
                $files[] = substr($file->getPathname(), \strlen($root) + 1);
            }
        }
        sort($files);

        return $files;
    }

    /**
     * The `templates` directories of the demos: `demo/<name>/templates` and deeper (`demo/<a>/<b>/templates`).
     *
     * @return list<string> Paths relative to `$root`, sorted
     */
    public static function demoTemplateDirectories(string $root): array
    {
        $demo = $root . '/demo';
        if (!is_dir($demo)) {
            return [];
        }
        $dirs = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($demo, \FilesystemIterator::SKIP_DOTS),
            static fn (\SplFileInfo $file): bool => $file->isDir() && !\in_array($file->getFilename(), self::SKIPPED, true),
        ), \RecursiveIteratorIterator::SELF_FIRST);
        /** @var \SplFileInfo $dir */
        foreach ($iterator as $dir) {
            if ('templates' === $dir->getFilename()) {
                $dirs[] = substr($dir->getPathname(), \strlen($root) + 1);
            }
        }
        sort($dirs);

        return $dirs;
    }
}
