<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Support\Testing;

use NowoTech\PhpQualityTools\Testing\ConfigurationThreadStackTestCase;

/**
 * Not run by PHPUnit: its source directory (created by the test that drives it) holds a file that does not compile.
 */
final class BrokenSourceThreadStackProbe extends ConfigurationThreadStackTestCase
{
    public static function directory(): string
    {
        return sys_get_temp_dir() . '/pqt-broken-source-' . getmypid();
    }

    protected static function configurationClasses(): array
    {
        return [];
    }

    protected static function sourceDirectory(): string
    {
        return self::directory();
    }
}
