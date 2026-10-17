<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Gate\Assets;

/**
 * Invalid asset budget configuration or unreadable manifest (exit code 2), or missing build output (exit code 1).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class AssetBudgetException extends \RuntimeException
{
    public const MISSING_BUILD = 1;

    public const INVALID = 2;
}
