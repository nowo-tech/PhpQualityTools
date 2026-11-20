<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing;

use PHPUnit\Framework\Assert;

/**
 * Memory-growth assertions for tests that drive ONE kept-alive kernel over many requests (FrankenPHP worker mode,
 * kernel reset off): a leak raises the heap request after request, so the heap measured after a GC run is sampled
 * while the requests run and the growth from the first to the last sample must stay small (or, as an option, the growth
 * between the medians of the two halves of the samples).
 *
 * ```php
 * $samples = [];
 * for ($i = 0; $i < 1000; ++$i) {
 *     $this->handleRequest($kernel);
 *     WorkerMemoryAssertions::sample($i, $samples);
 * }
 * WorkerMemoryAssertions::assertFlat($samples);
 * ```
 *
 * Needs `phpunit/phpunit`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class WorkerMemoryAssertions
{
    /** Requests before the first sample (warm-up), and the distance between two samples. */
    public const WARM_UP = 50;

    public const EVERY = 50;

    /** The growth that is still "flat": 4 MiB. */
    public const MARGIN = 4 * 1024 * 1024;

    /**
     * Appends the collected heap size when `$request` is a sampling point: every {@see self::EVERY} requests after
     * the {@see self::WARM_UP}.
     *
     * @param list<int> $samples
     */
    public static function sample(int $request, array &$samples, int $warmUp = self::WARM_UP, int $every = self::EVERY): void
    {
        if ($request >= $warmUp && 0 === $request % $every) {
            $samples[] = self::collectedMemory();
        }
    }

    /**
     * The growth over the samples: the last minus the first (the samples are taken after the warm-up). This is what
     * the per-bundle copies assert. With `$medians`, the growth between the medians of the first and the second half
     * of the samples instead: a leak raises every later sample, so it moves the median, while one late reallocation
     * step of a long-lived table does not (it would fail a first-to-last comparison).
     *
     * @param list<int> $samples At least two
     */
    public static function growth(array $samples, bool $medians = false): int
    {
        if (!$medians) {
            return $samples[\count($samples) - 1] - $samples[0];
        }
        $half = intdiv(\count($samples), 2);

        return self::median(\array_slice($samples, $half)) - self::median(\array_slice($samples, 0, $half));
    }

    /**
     * The upper median (the element at count / 2 of the sorted values).
     *
     * @param list<int> $values At least one
     */
    public static function median(array $values): int
    {
        sort($values);

        return $values[intdiv(\count($values), 2)];
    }

    /**
     * The heap size in bytes after a garbage-collection run.
     */
    public static function collectedMemory(): int
    {
        gc_collect_cycles();

        return memory_get_usage();
    }

    /**
     * Fails when the memory grew by `$margin` (default {@see self::MARGIN}, 4 MiB) or more: last sample minus first by
     * default (the effective margin is the whole growth allowed over all the sampled requests, so with 1000 requests
     * and the default sampling it is 4 MiB over 900 requests), or the median-halves growth with `$medians`.
     *
     * @param list<int> $samples At least two
     */
    public static function assertFlat(array $samples, int $margin = self::MARGIN, string $message = 'memory stays flat over many requests', bool $medians = false): void
    {
        Assert::assertGreaterThanOrEqual(2, \count($samples), 'Too few memory samples: raise the number of requests.');
        Assert::assertLessThan($margin, self::growth($samples, $medians), $message . ' (samples: ' . implode(', ', $samples) . ')');
    }
}
