<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Coverage;

/**
 * Raised when a Clover report cannot be read. The code is the CLI exit code to use.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class CloverException extends \RuntimeException
{
    /** Report file missing (exit 1, same as the original shell gate). */
    public const NOT_FOUND = 1;

    /** Report unreadable or without project metrics (exit 2). */
    public const INVALID = 2;
}
