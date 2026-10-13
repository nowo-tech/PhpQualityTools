<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * One failed boundary check: a message plus the offending `file:line` entries (if any).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class Violation
{
    /**
     * @param list<string> $details
     */
    public function __construct(
        public readonly string $message,
        public readonly array $details = [],
    ) {
    }
}
