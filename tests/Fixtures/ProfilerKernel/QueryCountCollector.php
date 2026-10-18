<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Fixtures\ProfilerKernel;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `db` collector with DoctrineDataCollector's getQueryCount() API.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class QueryCountCollector extends AbstractDataCollector
{
    public function __construct(private readonly QueryLog $log)
    {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data['count'] = $this->log->count;
    }

    public function getQueryCount(): int
    {
        $count = $this->data['count'] ?? 0;

        return \is_int($count) ? $count : 0;
    }

    public function getName(): string
    {
        return 'db';
    }

    public static function getTemplate(): ?string
    {
        return null;
    }
}
