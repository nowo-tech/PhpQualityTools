<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Support\Coverage;

/**
 * Stands for a php-code-coverage (up to 11) `CodeCoverage` object in a `--coverage-php` file, with a chosen driver.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class FakeLegacyCoverage
{
    public function __construct(
        private FakeSerializedData $data,
        private string $driver,
    ) {
    }

    public function getData(): FakeSerializedData
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTests(): array
    {
        return [];
    }

    public function driverIsPcov(): bool
    {
        return 'pcov' === $this->driver;
    }

    public function driverIsXdebug(): bool
    {
        return 'xdebug' === $this->driver;
    }
}
