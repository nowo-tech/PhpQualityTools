<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use NowoTech\PhpQualityTools\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for Plugin file installation and Composer scripts.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
class PluginInstallationTest extends TestCase
{
    private string $tempDir;
    private string $vendorDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/php-quality-tools-install-' . uniqid();
        $this->vendorDir = $this->tempDir . '/vendor';
        mkdir($this->tempDir, 0o777, true);
        mkdir($this->vendorDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function createPluginWithComposer(string $composerJsonContent = '{"name":"test/project","require":{}}'): Plugin
    {
        $composerJsonPath = $this->tempDir . '/composer.json';
        file_put_contents($composerJsonPath, $composerJsonContent);

        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $config = $this->createMock(Config::class);
        $io = $this->createMock(IOInterface::class);
        $localRepo = $this->createMock(\Composer\Repository\InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->willReturn(null);
        $repoManager = $this->createMock(\Composer\Repository\RepositoryManager::class);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);

        $config->method('get')->with('vendor-dir')->willReturn($this->vendorDir);
        $composer->method('getConfig')->willReturn($config);
        $composer->method('getRepositoryManager')->willReturn($repoManager);
        $plugin->activate($composer, $io);

        return $plugin;
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

    public function testInstallFilesCopiesConfigToProject(): void
    {
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->atLeastOnce())->method('write')->with($this->stringContains('php-quality-tools:'));

        $plugin = $this->createPluginWithComposer();
        $this->invokePrivateMethod($plugin, 'installFiles', [$io]);

        $this->assertFileExists($this->tempDir . '/.rector.php');
        $this->assertFileExists($this->tempDir . '/.php-cs-fixer.php');
    }

    public function testInstallFilesSkipsExistingFiles(): void
    {
        file_put_contents($this->tempDir . '/.rector.php', '<?php // existing');
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->atLeastOnce())->method('write');

        $plugin = $this->createPluginWithComposer();
        $this->invokePrivateMethod($plugin, 'installFiles', [$io]);

        $rectorContent = file_get_contents($this->tempDir . '/.rector.php');
        $this->assertNotFalse($rectorContent);
        $this->assertStringContainsString('existing', $rectorContent);
    }

    public function testGetFilesToInstallReturnsGenericFiles(): void
    {
        $plugin = $this->createPluginWithComposer();
        $files = $this->invokePrivateMethod($plugin, 'getFilesToInstall', ['generic', null]);

        $this->assertIsArray($files);
        $this->assertArrayHasKey('config/generic/.rector.php', $files);
        $this->assertArrayHasKey('config/generic/.php-cs-fixer.php', $files);
    }

    public function testGetFilesToInstallIncludesTwigWhenTwigInstalled(): void
    {
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $config = $this->createMock(Config::class);
        $io = $this->createMock(IOInterface::class);
        $localRepo = $this->createMock(\Composer\Repository\InstalledRepositoryInterface::class);
        $repoManager = $this->createMock(\Composer\Repository\RepositoryManager::class);

        $localRepo->method('findPackage')
            ->willReturnCallback(fn (string $name): ?\Composer\Package\Package => $name === 'twig/twig' ? new \Composer\Package\Package('twig/twig', '3.0.0', '3.0.0') : null);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);
        $config->method('get')->with('vendor-dir')->willReturn($this->vendorDir);
        $composer->method('getConfig')->willReturn($config);
        $composer->method('getRepositoryManager')->willReturn($repoManager);
        $plugin->activate($composer, $io);

        $files = $this->invokePrivateMethod($plugin, 'getFilesToInstall', ['generic', null]);
        $this->assertArrayHasKey('config/generic/.twig-cs-fixer.php', $files);
    }

    public function testGetFilesToInstallShowsTwigCommentWhenGenericAndNoTwig(): void
    {
        $plugin = $this->createPluginWithComposer();
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('write')
            ->with($this->stringContains('Twig not detected'));

        $this->invokePrivateMethod($plugin, 'getFilesToInstall', ['generic', $io]);
    }

    public function testGetFilesToInstallFallbackToGenericWhenFrameworkHasNoConfig(): void
    {
        $plugin = $this->createPluginWithComposer();
        $files = $this->invokePrivateMethod($plugin, 'getFilesToInstall', ['yii', null]);
        $this->assertArrayHasKey('config/generic/.rector.php', $files);
        $this->assertArrayHasKey('config/generic/.php-cs-fixer.php', $files);
    }

    public function testGetFilesToInstallTwigFallbackToGenericWhenFrameworkHasNoTwigConfig(): void
    {
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $config = $this->createMock(Config::class);
        $config->method('get')->with('vendor-dir')->willReturn($this->vendorDir);
        $composer->method('getConfig')->willReturn($config);
        $localRepo = $this->createMock(\Composer\Repository\InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')
            ->willReturnCallback(fn (string $name): ?\Composer\Package\Package => $name === 'twig/twig' ? new \Composer\Package\Package('twig/twig', '3.0.0', '3.0.0') : null);
        $repoManager = $this->createMock(\Composer\Repository\RepositoryManager::class);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);
        $composer->method('getRepositoryManager')->willReturn($repoManager);
        $plugin->activate($composer, $this->createMock(IOInterface::class));

        $files = $this->invokePrivateMethod($plugin, 'getFilesToInstall', ['yii', null]);
        $this->assertArrayHasKey('config/generic/.twig-cs-fixer.php', $files);
    }

    public function testInstallComposerScriptsAddsNewScripts(): void
    {
        $composerJson = json_encode([
            'name' => 'test/project',
            'require' => [],
            'scripts' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($composerJson);
        $composerJson = str_replace('    ', '  ', $composerJson);
        file_put_contents($this->tempDir . '/composer.json', $composerJson);

        $plugin = $this->createPluginWithComposer($composerJson);
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->atLeastOnce())->method('write')->with($this->stringContains('Added'));

        $this->invokePrivateMethod($plugin, 'installComposerScripts', [$io]);

        $jsonContent = file_get_contents($this->tempDir . '/composer.json');
        $this->assertNotFalse($jsonContent);
        $content = json_decode($jsonContent, true);
        $this->assertArrayHasKey('scripts', $content);
        $this->assertArrayHasKey('fix', $content['scripts']);
        $this->assertArrayHasKey('rector', $content['scripts']);
    }

    public function testInstallComposerScriptsInitializesScriptsWhenMissing(): void
    {
        $composerJson = '{"name":"test/project","require":{}}';
        file_put_contents($this->tempDir . '/composer.json', $composerJson);

        $plugin = $this->createPluginWithComposer($composerJson);
        $io = $this->createMock(IOInterface::class);
        $this->invokePrivateMethod($plugin, 'installComposerScripts', [$io]);

        $jsonContent = file_get_contents($this->tempDir . '/composer.json');
        $this->assertNotFalse($jsonContent);
        $content = json_decode($jsonContent, true);
        $this->assertArrayHasKey('scripts', $content);
        $this->assertNotEmpty($content['scripts']);
    }

    public function testInstallComposerScriptsDoesNotOverwriteExisting(): void
    {
        $composerJson = json_encode([
            'name' => 'test/project',
            'require' => [],
            'scripts' => [
                'fix' => 'custom-fix-command',
                'fix:check' => 'php-cs-fixer fix --dry-run',
                'rector' => 'rector process',
                'rector:check' => 'rector process --dry-run',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($composerJson);
        file_put_contents($this->tempDir . '/composer.json', $composerJson);

        $plugin = $this->createPluginWithComposer($composerJson);
        $io = $this->createMock(IOInterface::class);
        $io->expects($this->atLeastOnce())->method('write')->with($this->stringContains('already exist'));

        $this->invokePrivateMethod($plugin, 'installComposerScripts', [$io]);

        $jsonContent = file_get_contents($this->tempDir . '/composer.json');
        $this->assertNotFalse($jsonContent);
        $content = json_decode($jsonContent, true);
        $this->assertSame('custom-fix-command', $content['scripts']['fix']);
    }

    public function testOnPostInstallCallsInstallFilesAndScripts(): void
    {
        file_put_contents($this->tempDir . '/composer.json', '{"name":"test","require":{}}');

        $plugin = $this->createPluginWithComposer();
        $io = $this->createMock(IOInterface::class);
        $event = $this->createMock(Event::class);
        $event->method('getIO')->willReturn($io);

        $plugin->onPostInstall($event);

        $this->assertFileExists($this->tempDir . '/.rector.php');
    }

    public function testOnPostUpdateCallsInstallFiles(): void
    {
        file_put_contents($this->tempDir . '/composer.json', '{"name":"test","require":{}}');

        $plugin = $this->createPluginWithComposer();
        $io = $this->createMock(IOInterface::class);
        $event = $this->createMock(Event::class);
        $event->method('getIO')->willReturn($io);

        $plugin->onPostUpdate($event);

        $this->assertFileExists($this->tempDir . '/.php-cs-fixer.php');
    }

    public function testOnPostUpdateCallsCheckDependenciesWhenInteractive(): void
    {
        file_put_contents($this->tempDir . '/composer.json', '{"name":"test","require":{}}');

        $io = $this->createMock(IOInterface::class);
        $io->method('isInteractive')->willReturn(true);
        $io->method('askConfirmation')->willReturn(false);

        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $config = $this->createMock(Config::class);
        $localRepo = $this->createMock(\Composer\Repository\InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->willReturn(null);
        $repoManager = $this->createMock(\Composer\Repository\RepositoryManager::class);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);
        $config->method('get')->with('vendor-dir')->willReturn($this->vendorDir);
        $composer->method('getConfig')->willReturn($config);
        $composer->method('getRepositoryManager')->willReturn($repoManager);
        $plugin->activate($composer, $io);

        $event = $this->createMock(Event::class);
        $event->method('getIO')->willReturn($io);

        $plugin->onPostUpdate($event);

        $this->assertFileExists($this->tempDir . '/.rector.php');
    }

    public function testOnPostUpdateSkipsCheckDependenciesWhenNonInteractive(): void
    {
        file_put_contents($this->tempDir . '/composer.json', '{"name":"test","require":{}}');

        $io = $this->createMock(IOInterface::class);
        $io->method('isInteractive')->willReturn(false);
        $io->expects($this->never())->method('askConfirmation');

        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $config = $this->createMock(Config::class);
        $localRepo = $this->createMock(\Composer\Repository\InstalledRepositoryInterface::class);
        $localRepo->method('findPackage')->willReturn(null);
        $repoManager = $this->createMock(\Composer\Repository\RepositoryManager::class);
        $repoManager->method('getLocalRepository')->willReturn($localRepo);
        $config->method('get')->with('vendor-dir')->willReturn($this->vendorDir);
        $composer->method('getConfig')->willReturn($config);
        $composer->method('getRepositoryManager')->willReturn($repoManager);
        $plugin->activate($composer, $io);

        $event = $this->createMock(Event::class);
        $event->method('getIO')->willReturn($io);

        $plugin->onPostUpdate($event);

        $this->assertFileExists($this->tempDir . '/.rector.php');
    }

    public function testDetectJsonIndentationUnusualSpaces(): void
    {
        $plugin = new Plugin();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('detectJsonIndentation');

        $json = "{\n   \"name\": \"test\"\n}";
        $indent = $method->invoke($plugin, $json);
        $this->assertSame('   ', $indent);
    }

    public function testDetectJsonIndentationTabs(): void
    {
        $plugin = new Plugin();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('detectJsonIndentation');

        $json = "{\n\t\"name\": \"test\"\n}";
        $indent = $method->invoke($plugin, $json);
        $this->assertSame("\t", $indent);
    }

    public function testDetectJsonIndentationTwoSpaces(): void
    {
        $plugin = new Plugin();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('detectJsonIndentation');

        $json = "{\n  \"name\": \"test\"\n}";
        $indent = $method->invoke($plugin, $json);
        $this->assertSame('  ', $indent);
    }

    public function testDetectJsonIndentationFourSpaces(): void
    {
        $plugin = new Plugin();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('detectJsonIndentation');

        $json = "{\n    \"name\": \"test\"\n}";
        $indent = $method->invoke($plugin, $json);
        $this->assertSame('    ', $indent);
    }

    public function testDetectJsonIndentationFallbackWhenNoMatch(): void
    {
        $plugin = new Plugin();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('detectJsonIndentation');

        $json = '{}';
        $indent = $method->invoke($plugin, $json);
        $this->assertSame('  ', $indent);
    }

    public function testEncodeJsonWithIndentationEmptyLineHandling(): void
    {
        $plugin = new Plugin();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('encodeJsonWithIndentation');

        $data = ['a' => 0, 'b' => 1];
        $result = $method->invoke($plugin, $data, '  ');
        $this->assertNotFalse($result);
        $this->assertStringContainsString('"a": 0', (string) $result);
    }

    public function testEncodeJsonWithIndentationFourSpacesReturnsAsIs(): void
    {
        $plugin = new Plugin();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('encodeJsonWithIndentation');

        $data = ['key' => 'value'];
        $result = $method->invoke($plugin, $data, '    ');
        $this->assertNotFalse($result);
        $this->assertStringContainsString('"key"', (string) $result);
        $this->assertStringContainsString('    ', (string) $result);
    }

    public function testInstallComposerScriptsWhenComposerJsonNotReadable(): void
    {
        $badDir = sys_get_temp_dir() . '/phpqt-bad-' . uniqid();
        mkdir($badDir, 0o777, true);
        mkdir($badDir . '/vendor', 0o777, true);
        mkdir($badDir . '/composer.json', 0o777, true);

        $plugin = new Plugin();
        $config = $this->createMock(Config::class);
        $config->method('get')->with('vendor-dir')->willReturn($badDir . '/vendor');
        $composer = $this->createMock(Composer::class);
        $composer->method('getConfig')->willReturn($config);
        $plugin->activate($composer, $this->createMock(IOInterface::class));

        $io = $this->createMock(IOInterface::class);
        $io->expects($this->once())->method('writeError')->with($this->logicalOr(
            $this->stringContains('Failed to read'),
            $this->stringContains('Failed to parse')
        ));

        $this->invokePrivateMethod($plugin, 'installComposerScripts', [$io]);

        rmdir($badDir . '/composer.json');
        rmdir($badDir . '/vendor');
        rmdir($badDir);
    }

    public function testEncodeJsonWithIndentationReturnsFalseForInvalidData(): void
    {
        $plugin = new Plugin();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('encodeJsonWithIndentation');

        $data = ['valid' => 'ok', 'invalid' => NAN];
        $result = $method->invoke($plugin, $data, '  ');
        $this->assertFalse($result);
    }
}
