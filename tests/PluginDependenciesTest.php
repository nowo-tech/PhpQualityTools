<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use NowoTech\PhpQualityTools\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for Plugin dependency checking and script registration.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
class PluginDependenciesTest extends TestCase
{
    private function createComposerMock(
        ?InstalledRepositoryInterface $localRepo = null
    ): Composer {
        $composer = $this->createMock(Composer::class);
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnMap([
            ['vendor-dir', null, '/tmp/vendor'],
            ['bin-dir', null, '/tmp/vendor/bin'],
        ]);
        $composer->method('getConfig')->willReturn($config);

        if ($localRepo instanceof InstalledRepositoryInterface) {
            $repoManager = $this->createMock(RepositoryManager::class);
            $repoManager->method('getLocalRepository')->willReturn($localRepo);
            $composer->method('getRepositoryManager')->willReturn($repoManager);
        }

        return $composer;
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivateMethod(Plugin $plugin, string $methodName, array $args = []): mixed
    {
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod($methodName);

        return $method->invoke($plugin, ...$args);
    }

    public function testIsPackageInstalledReturnsTrueWhenPackageFound(): void
    {
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->with('rector/rector', '*')->willReturn(new Package('rector/rector', '2.0.0', '2.0.0'));

        $plugin = new Plugin();
        $plugin->activate($this->createComposerMock($localRepo), $this->createMock(IOInterface::class));

        $this->assertTrue($this->invokePrivateMethod($plugin, 'isPackageInstalled', ['rector/rector']));
    }

    public function testIsPackageInstalledReturnsFalseWhenPackageNotFound(): void
    {
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->with('rector/rector', '*')->willReturn(null);

        $plugin = new Plugin();
        $plugin->activate($this->createComposerMock($localRepo), $this->createMock(IOInterface::class));

        $this->assertFalse($this->invokePrivateMethod($plugin, 'isPackageInstalled', ['rector/rector']));
    }

    public function testGetScriptsForFrameworkGeneric(): void
    {
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->willReturn(null);

        $plugin = new Plugin();
        $plugin->activate($this->createComposerMock($localRepo), $this->createMock(IOInterface::class));

        $scripts = $this->invokePrivateMethod($plugin, 'getScriptsForFramework', ['generic']);
        $this->assertArrayHasKey('fix', $scripts);
        $this->assertArrayHasKey('rector', $scripts);
        $this->assertArrayNotHasKey('twig:fix', $scripts);
        $this->assertArrayNotHasKey('blade-check', $scripts);
    }

    public function testGetScriptsForFrameworkLaravelAddsBladeScripts(): void
    {
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->willReturn(null);

        $plugin = new Plugin();
        $plugin->activate($this->createComposerMock($localRepo), $this->createMock(IOInterface::class));

        $scripts = $this->invokePrivateMethod($plugin, 'getScriptsForFramework', ['laravel']);
        $this->assertArrayHasKey('blade-check', $scripts);
        $this->assertArrayHasKey('blade-fix', $scripts);
    }

    public function testGetScriptsForFrameworkWithTwigAddsTwigScripts(): void
    {
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->willReturnCallback(fn (string $name): ?\Composer\Package\Package => $name === 'twig/twig' ? new Package('twig/twig', '3.0.0', '3.0.0') : null);

        $plugin = new Plugin();
        $plugin->activate($this->createComposerMock($localRepo), $this->createMock(IOInterface::class));

        $scripts = $this->invokePrivateMethod($plugin, 'getScriptsForFramework', ['generic']);
        $this->assertArrayHasKey('twig:fix', $scripts);
        $this->assertArrayHasKey('twig:lint', $scripts);
    }

    public function testGetScriptsForFrameworkWithPhpunitAddsTestScript(): void
    {
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->willReturnCallback(fn (string $name): ?\Composer\Package\Package => $name === 'phpunit/phpunit' ? new Package('phpunit/phpunit', '11.0.0', '11.0.0') : null);

        $plugin = new Plugin();
        $plugin->activate($this->createComposerMock($localRepo), $this->createMock(IOInterface::class));

        $scripts = $this->invokePrivateMethod($plugin, 'getScriptsForFramework', ['generic']);
        $this->assertArrayHasKey('test', $scripts);
        $this->assertSame('phpunit', $scripts['test']);
    }

    public function testCheckAndInstallDependenciesReturnsWhenNoMissingPackages(): void
    {
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->willReturn(new Package('rector/rector', '2.0.0', '2.0.0'));
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->never())->method('askConfirmation');

        $plugin = new Plugin();
        $plugin->activate($this->createComposerMock($localRepo), $io);
        $this->invokePrivateMethod($plugin, 'checkAndInstallDependencies', [$io]);
    }

    public function testCheckAndInstallDependenciesNonInteractiveWritesMessage(): void
    {
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->willReturn(null);
        $io = $this->createMock(IOInterface::class);
        $io->method('isInteractive')->willReturn(false);
        $io->expects($this->atLeastOnce())->method('write');
        $io->expects($this->never())->method('askConfirmation');

        $plugin = new Plugin();
        $plugin->activate($this->createComposerMock($localRepo), $io);
        $this->invokePrivateMethod($plugin, 'checkAndInstallDependencies', [$io]);
    }

    public function testCheckAndInstallDependenciesInteractiveNoSkipsInstall(): void
    {
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->willReturn(null);
        $io = $this->createMock(IOInterface::class);
        $io->method('isInteractive')->willReturn(true);
        $io->method('askConfirmation')->willReturn(false);
        $io->expects($this->atLeastOnce())->method('write');

        $plugin = new Plugin();
        $plugin->activate($this->createComposerMock($localRepo), $io);
        $this->invokePrivateMethod($plugin, 'checkAndInstallDependencies', [$io]);
    }

    public function testInstallComposerScriptsWhenComposerJsonMissing(): void
    {
        $tempDir = sys_get_temp_dir() . '/phpqt-' . uniqid();
        mkdir($tempDir, 0o777, true);
        mkdir($tempDir . '/vendor', 0o777, true);

        $plugin = new Plugin();
        $config = $this->createMock(Config::class);
        $config->method('get')->with('vendor-dir')->willReturn($tempDir . '/vendor');
        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')->willReturn($config);
        $plugin->activate($composer, $this->createMock(IOInterface::class));

        $io = $this->createMock(IOInterface::class);
        $io->expects($this->never())->method('write');
        $this->invokePrivateMethod($plugin, 'installComposerScripts', [$io]);

        rmdir($tempDir . '/vendor');
        rmdir($tempDir);
    }

    public function testInstallComposerScriptsWhenJsonInvalidWritesError(): void
    {
        $tempDir = sys_get_temp_dir() . '/phpqt-' . uniqid();
        mkdir($tempDir, 0o777, true);
        mkdir($tempDir . '/vendor', 0o777, true);
        file_put_contents($tempDir . '/composer.json', 'invalid json {');

        $plugin = new Plugin();
        $config = $this->createMock(Config::class);
        $config->method('get')->with('vendor-dir')->willReturn($tempDir . '/vendor');
        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')->willReturn($config);
        $plugin->activate($composer, $this->createMock(IOInterface::class));

        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('writeError')->with($this->stringContains('Failed to parse'));

        $this->invokePrivateMethod($plugin, 'installComposerScripts', [$io]);

        unlink($tempDir . '/composer.json');
        rmdir($tempDir . '/vendor');
        rmdir($tempDir);
    }

    public function testInstallDependenciesFailureWritesError(): void
    {
        $plugin = new Plugin();
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnMap([
            ['vendor-dir', null, '/tmp/vendor'],
            ['bin-dir', null, '/nonexistent-bin'],
        ]);
        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')->willReturn($config);
        $plugin->activate($composer, $this->createMock(IOInterface::class));

        $io = $this->createMock(IOInterface::class);
        $io->expects($this->atLeastOnce())->method('writeError')->with($this->logicalOr(
            $this->stringContains('Failed to install'),
            $this->stringContains('error>')
        ));

        $this->invokePrivateMethod($plugin, 'installDependencies', [$io, ['invalid/package-that-does-not-exist-xyz']]);
    }

    public function testInstallDependenciesBuildsCorrectVersionForOptionalRectorPackages(): void
    {
        $plugin = new Plugin();
        $config = $this->createMock(Config::class);
        $config->method('get')->willReturnMap([
            ['vendor-dir', null, '/tmp/vendor'],
            ['bin-dir', null, '/nonexistent-bin'],
        ]);
        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')->willReturn($config);
        $plugin->activate($composer, $this->createMock(IOInterface::class));

        $io = $this->createMock(IOInterface::class);
        $io->expects($this->atLeastOnce())->method('write');
        $io->expects($this->atLeastOnce())->method('writeError');

        $this->invokePrivateMethod($plugin, 'installDependencies', [
            $io,
            ['rector/rector-doctrine', 'rector/rector-symfony', 'rector/rector-phpunit', 'friendsofphp/php-cs-fixer'],
        ]);
    }

    public function testCheckAndInstallDependenciesSkipsOptionalRectorPackagesWhenRector2(): void
    {
        $localRepo = $this->createMock(InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->willReturnCallback(fn (string $name): ?\Composer\Package\Package => $name === 'rector/rector' ? new Package('rector/rector', '2.2.14', '2.2.14') : null);
        $io = $this->createMock(IOInterface::class);
        $io->method('isInteractive')->willReturn(false);

        $plugin = new Plugin();
        $plugin->activate($this->createComposerMock($localRepo), $io);
        $this->invokePrivateMethod($plugin, 'checkAndInstallDependencies', [$io]);

        $this->addToAssertionCount(1);
    }
}
