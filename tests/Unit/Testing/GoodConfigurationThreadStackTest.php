<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing;

use NowoTech\PhpQualityTools\Testing\ConfigurationThreadStackTestCase;
use NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Config\GoodConfiguration;
use NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Config\RequiredKeyConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * How a bundle adopts ConfigurationThreadStackTestCase: two configuration classes (one with a required key without a
 * default) and this package's own sources.
 */
#[CoversClass(ConfigurationThreadStackTestCase::class)]
final class GoodConfigurationThreadStackTest extends ConfigurationThreadStackTestCase
{
    protected static function configurationClasses(): array
    {
        return [
            'Good' => GoodConfiguration::class,
            'RequiredKey' => RequiredKeyConfiguration::class,
        ];
    }

    protected static function sourceDirectory(): string
    {
        return __DIR__ . '/../../../src';
    }
}
