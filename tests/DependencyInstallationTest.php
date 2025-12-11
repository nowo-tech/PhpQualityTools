<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\InstalledRepositoryInterface;
use NowoTech\PhpQualityTools\Plugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.com>
 * @see    https://github.com/HecFranco
 */
class DependencyInstallationTest extends TestCase
{
    public function testSuggestedPackagesConstantExists(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $this->assertTrue($reflection->hasConstant('SUGGESTED_PACKAGES'));
    }

    public function testSuggestedPackagesContainsAllFrameworks(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $constant = $reflection->getConstant('SUGGESTED_PACKAGES');

        $expectedFrameworks = [
            'generic',
            'symfony',
            'laravel',
            'yii',
            'cakephp',
            'laminas',
            'codeigniter',
            'slim',
        ];

        foreach ($expectedFrameworks as $framework) {
            $this->assertArrayHasKey($framework, $constant, "Framework {$framework} should have suggested packages");
        }
    }

    public function testSymfonySuggestedPackages(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $constant = $reflection->getConstant('SUGGESTED_PACKAGES');

        $symfonyPackages = $constant['symfony'] ?? [];

        $this->assertArrayHasKey('rector/rector', $symfonyPackages);
        $this->assertArrayHasKey('rector/rector-symfony', $symfonyPackages);
        $this->assertArrayHasKey('rector/rector-doctrine', $symfonyPackages);
        $this->assertArrayHasKey('rector/rector-phpunit', $symfonyPackages);
        $this->assertArrayHasKey('friendsofphp/php-cs-fixer', $symfonyPackages);
        $this->assertArrayHasKey('vincentlanglet/twig-cs-fixer', $symfonyPackages);
    }

    public function testLaravelSuggestedPackages(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $constant = $reflection->getConstant('SUGGESTED_PACKAGES');

        $laravelPackages = $constant['laravel'] ?? [];

        $this->assertArrayHasKey('rector/rector', $laravelPackages);
        $this->assertArrayHasKey('driftingly/rector-laravel', $laravelPackages);
        $this->assertArrayHasKey('friendsofphp/php-cs-fixer', $laravelPackages);
    }

    public function testGenericSuggestedPackages(): void
    {
        $reflection = new \ReflectionClass(Plugin::class);
        $constant = $reflection->getConstant('SUGGESTED_PACKAGES');

        $genericPackages = $constant['generic'] ?? [];

        $this->assertArrayHasKey('rector/rector', $genericPackages);
        $this->assertArrayHasKey('friendsofphp/php-cs-fixer', $genericPackages);
        $this->assertArrayHasKey('vincentlanglet/twig-cs-fixer', $genericPackages);
    }
}

