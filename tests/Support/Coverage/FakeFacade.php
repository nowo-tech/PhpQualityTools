<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Support\Coverage;

/**
 * Stands for php-code-coverage 12+'s `Report\Facade`: records the payload it was built from and writes it as JSON
 * instead of a Clover report.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final readonly class FakeFacade
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(private array $payload)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromSerializedData(array $payload): self
    {
        return new self($payload);
    }

    public function renderClover(string $target, ?string $name = null): void
    {
        $data = $this->payload['codeCoverage'];
        \assert($data instanceof FakeSerializedData);
        file_put_contents($target, json_encode([
            'name' => $name,
            'basePath' => $this->payload['basePath'],
            'lines' => $data->lines,
            'testIds' => $data->testIds,
            'countsHits' => $data->countsHits,
            'tests' => $this->payload['testResults'],
        ]));
    }
}
