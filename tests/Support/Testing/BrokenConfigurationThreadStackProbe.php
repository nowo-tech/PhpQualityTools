<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Support\Testing;

use NowoTech\PhpQualityTools\Testing\ConfigurationThreadStackTestCase;
use NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Config\BrokenConfiguration;

/**
 * Not run by PHPUnit (the file name does not end in Test.php): ConfigurationThreadStackTestCaseTest calls its test
 * methods directly and expects them to fail.
 */
final class BrokenConfigurationThreadStackProbe extends ConfigurationThreadStackTestCase
{
    protected static function configurationClasses(): array
    {
        return ['Broken' => BrokenConfiguration::class];
    }

    protected static function sourceDirectory(): string
    {
        return \dirname(__DIR__, 3) . '/src/Gate/Svg';
    }
}
