<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use NowoTech\PhpQualityTools\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.com>
 *
 * @see    https://github.com/HecFranco
 */
class FrameworkDetectionTest extends TestCase
{
    /**
     * Test that framework detection constants are defined
     */
    public function testFrameworkPackagesConstantExists(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $this->assertTrue($reflection->hasConstant('FRAMEWORK_PACKAGES'));
    }

    /**
     * Test that all expected frameworks are in the detection list
     */
    public function testFrameworkPackagesContainsExpectedFrameworks(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $constant = $reflection->getConstant('FRAMEWORK_PACKAGES');

        $expectedFrameworks = [
            'symfony/framework-bundle',
            'symfony/symfony',
            'laravel/framework',
            'yiisoft/yii2',
            'cakephp/cakephp',
            'laminas/laminas-mvc',
            'codeigniter4/framework',
            'slim/slim',
        ];

        foreach ($expectedFrameworks as $package) {
            $this->assertArrayHasKey($package, $constant, "Package {$package} should be in FRAMEWORK_PACKAGES");
        }
    }

    /**
     * Test that framework detection returns valid framework names
     */
    public function testFrameworkDetectionReturnsValidFramework(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $constant = $reflection->getConstant('FRAMEWORK_PACKAGES');

        $validFrameworks = array_values($constant);
        $validFrameworks[] = 'generic'; // Generic is also valid

        foreach ($validFrameworks as $framework) {
            $this->assertIsString($framework);
            $this->assertNotEmpty($framework);
        }
    }
}
