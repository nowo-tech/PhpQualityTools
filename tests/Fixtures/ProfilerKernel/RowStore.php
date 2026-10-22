<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Fixtures\ProfilerKernel;

/**
 * Row count persisted in a file: KernelBrowser reboots the kernel (and its services) before each request.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class RowStore
{
    public function __construct(private string $file)
    {
    }

    public function count(): int
    {
        return is_file($this->file) ? (int) file_get_contents($this->file) : 0;
    }

    public function save(int $rows): void
    {
        if (!is_dir(\dirname($this->file))) {
            mkdir(\dirname($this->file), 0o777, true);
        }
        file_put_contents($this->file, (string) $rows);
    }
}
