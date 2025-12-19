<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Repository\RepositoryManager;
use Composer\Repository\InstalledRepositoryInterface;
use NowoTech\PhpQualityTools\Plugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for Rector version detection functionality.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.com>
 * @see    https://github.com/HecFranco
 */
class RectorVersionDetectionTest extends TestCase
{
    /**
     * Test that getRectorVersion method exists and is private.
     */
    public function testGetRectorVersionMethodExists(): void
    {
        $reflection = new ReflectionClass(Plugin::class);
        $this->assertTrue($reflection->hasMethod('getRectorVersion'));
        
        $method = $reflection->getMethod('getRectorVersion');
        $this->assertTrue($method->isPrivate());
        $this->assertEquals('int', $method->getReturnType()->getName());
    }

    /**
     * Test getRectorVersion returns 1 when Rector is not installed.
     */
    public function testGetRectorVersionReturnsOneWhenNotInstalled(): void
    {
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $config = $this->createMock(Config::class);
        $io = $this->createMock(IOInterface::class);
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $localRepository = $this->createMock(InstalledRepositoryInterface::class);

        $config->method('get')
            ->with('vendor-dir')
            ->willReturn('/tmp/vendor');

        $composer->method('getConfig')
            ->willReturn($config);

        $composer->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        $repositoryManager->method('getLocalRepository')
            ->willReturn($localRepository);

        $localRepository->method('findPackage')
            ->with('rector/rector', '*')
            ->willReturn(null);

        $plugin->activate($composer, $io);

        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('getRectorVersion');
        $method->setAccessible(true);

        $version = $method->invoke($plugin);
        $this->assertEquals(1, $version);
    }

    /**
     * Test getRectorVersion returns 1 for Rector 1.x versions.
     */
    public function testGetRectorVersionReturnsOneForVersionOne(): void
    {
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $config = $this->createMock(Config::class);
        $io = $this->createMock(IOInterface::class);
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $localRepository = $this->createMock(InstalledRepositoryInterface::class);
        $rectorPackage = $this->createMock(Package::class);

        $config->method('get')
            ->with('vendor-dir')
            ->willReturn('/tmp/vendor');

        $composer->method('getConfig')
            ->willReturn($config);

        $composer->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        $repositoryManager->method('getLocalRepository')
            ->willReturn($localRepository);

        $localRepository->method('findPackage')
            ->with('rector/rector', '*')
            ->willReturn($rectorPackage);

        $rectorPackage->method('getVersion')
            ->willReturn('1.2.10');

        $plugin->activate($composer, $io);

        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('getRectorVersion');
        $method->setAccessible(true);

        $version = $method->invoke($plugin);
        $this->assertEquals(1, $version);
    }

    /**
     * Test getRectorVersion returns 2 for Rector 2.x versions.
     */
    public function testGetRectorVersionReturnsTwoForVersionTwo(): void
    {
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $config = $this->createMock(Config::class);
        $io = $this->createMock(IOInterface::class);
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $localRepository = $this->createMock(InstalledRepositoryInterface::class);
        $rectorPackage = $this->createMock(Package::class);

        $config->method('get')
            ->with('vendor-dir')
            ->willReturn('/tmp/vendor');

        $composer->method('getConfig')
            ->willReturn($config);

        $composer->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        $repositoryManager->method('getLocalRepository')
            ->willReturn($localRepository);

        $localRepository->method('findPackage')
            ->with('rector/rector', '*')
            ->willReturn($rectorPackage);

        $rectorPackage->method('getVersion')
            ->willReturn('2.2.14');

        $plugin->activate($composer, $io);

        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('getRectorVersion');
        $method->setAccessible(true);

        $version = $method->invoke($plugin);
        $this->assertEquals(2, $version);
    }

    /**
     * Test getRectorVersion handles invalid version strings gracefully.
     */
    public function testGetRectorVersionHandlesInvalidVersion(): void
    {
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $config = $this->createMock(Config::class);
        $io = $this->createMock(IOInterface::class);
        $repositoryManager = $this->createMock(RepositoryManager::class);
        $localRepository = $this->createMock(InstalledRepositoryInterface::class);
        $rectorPackage = $this->createMock(Package::class);

        $config->method('get')
            ->with('vendor-dir')
            ->willReturn('/tmp/vendor');

        $composer->method('getConfig')
            ->willReturn($config);

        $composer->method('getRepositoryManager')
            ->willReturn($repositoryManager);

        $repositoryManager->method('getLocalRepository')
            ->willReturn($localRepository);

        $localRepository->method('findPackage')
            ->with('rector/rector', '*')
            ->willReturn($rectorPackage);

        $rectorPackage->method('getVersion')
            ->willReturn('invalid-version');

        $plugin->activate($composer, $io);

        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('getRectorVersion');
        $method->setAccessible(true);

        $version = $method->invoke($plugin);
        // Should default to 1 for invalid versions
        $this->assertEquals(1, $version);
    }
}

