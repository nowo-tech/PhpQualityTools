<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\ModuleBoundary;

/**
 * Deny-list edge: files of the `from` modules must not import the `to` modules,
 * except the files matching `except`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ForbiddenEdge
{
    /**
     * @param list<string> $from   Source module names
     * @param list<string> $to     Target module names
     * @param list<string> $except Path patterns of reviewed exceptions
     */
    public function __construct(
        public readonly array $from,
        public readonly array $to,
        public readonly array $except = [],
        public readonly string $message = '',
    ) {
    }
}
