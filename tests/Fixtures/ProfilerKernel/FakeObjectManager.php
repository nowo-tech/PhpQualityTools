<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Fixtures\ProfilerKernel;

/**
 * Stands in for the ORM entity manager: persisted rows are written on flush().
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class FakeObjectManager
{
    private int $pending = 0;

    public int $flushes = 0;

    public int $clears = 0;

    public function __construct(private readonly RowStore $store)
    {
    }

    public function persistRows(int $rows): void
    {
        $this->pending += $rows;
    }

    public function flush(): void
    {
        $this->store->save($this->store->count() + $this->pending);
        $this->pending = 0;
        ++$this->flushes;
    }

    public function clear(): void
    {
        ++$this->clears;
    }
}
