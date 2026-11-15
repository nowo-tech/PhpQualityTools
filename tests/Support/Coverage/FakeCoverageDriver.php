<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Support\Coverage;

use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\Driver\Driver;

/**
 * A coverage driver that collects nothing: lets the tests build real php-code-coverage objects (and their
 * `--coverage-php` files and Clover reports) from chosen per-test data, without PCOV or Xdebug.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class FakeCoverageDriver extends Driver
{
    public function nameAndVersion(): string
    {
        return 'fake';
    }

    public function start(): void
    {
    }

    public function stop(): RawCodeCoverageData
    {
        return RawCodeCoverageData::fromXdebugWithoutPathCoverage([]);
    }
}
