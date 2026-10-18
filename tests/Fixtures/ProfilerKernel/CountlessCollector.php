<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Fixtures\ProfilerKernel;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A `db` collector without getQueryCount().
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CountlessCollector extends AbstractDataCollector
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
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
