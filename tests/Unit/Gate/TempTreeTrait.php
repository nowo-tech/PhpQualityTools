<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Gate;

/**
 * Writes throw-away file trees under the system temp directory (removed after each test).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
trait TempTreeTrait
{
    /** @var list<string> */
    private array $tempDirs = [];

    /**
     * @param array<string, string|null> $files relative path => content (null = not created)
     */
    private function makeTree(array $files): string
    {
        $root = sys_get_temp_dir() . '/pqt-tree-' . bin2hex(random_bytes(6));
        mkdir($root, 0o777, true);
        $this->tempDirs[] = $root;
        foreach ($files as $path => $content) {
            if (null === $content) {
                continue;
            }
            $file = $root . '/' . $path;
            if (!is_dir(\dirname($file))) {
                mkdir(\dirname($file), 0o777, true);
            }
            file_put_contents($file, $content);
        }

        return $root;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            /** @var \SplFileInfo $info */
            foreach ($iterator as $info) {
                $info->isDir() ? rmdir($info->getPathname()) : unlink($info->getPathname());
            }
            rmdir($dir);
        }
        $this->tempDirs = [];
        parent::tearDown();
    }
}
