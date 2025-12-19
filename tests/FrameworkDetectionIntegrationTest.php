<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Repository\RepositoryManager;
use NowoTech\PhpQualityTools\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Integration tests for framework detection functionality.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.com>
 * @see    https://github.com/HecFranco
 */
class FrameworkDetectionIntegrationTest extends TestCase
{
    private string $tempDir;
    private string $tempComposerJson;

    protected function setUp(): void
    {
        // Create a temporary directory for testing
        $this->tempDir = sys_get_temp_dir() . '/php-quality-tools-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->tempComposerJson = $this->tempDir . '/composer.json';
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        if (file_exists($this->tempComposerJson)) {
            unlink($this->tempComposerJson);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    /**
     * Test framework detection for Symfony projects.
     */
    public function testDetectFrameworkSymfony(): void
    {
        $this->createComposerJson(['symfony/framework-bundle' => '^6.0']);
        $framework = $this->detectFrameworkForProject();
        $this->assertEquals('symfony', $framework);
    }

    /**
     * Test framework detection for Laravel projects.
     */
    public function testDetectFrameworkLaravel(): void
    {
        $this->createComposerJson(['laravel/framework' => '^10.0']);
        $framework = $this->detectFrameworkForProject();
        $this->assertEquals('laravel', $framework);
    }

    /**
     * Test framework detection for generic projects (no framework).
     */
    public function testDetectFrameworkGeneric(): void
    {
        $this->createComposerJson(['some/package' => '^1.0']);
        $framework = $this->detectFrameworkForProject();
        $this->assertEquals('generic', $framework);
    }

    /**
     * Test framework detection when composer.json doesn't exist.
     */
    public function testDetectFrameworkWhenComposerJsonMissing(): void
    {
        // Don't create composer.json
        $framework = $this->detectFrameworkForProject();
        $this->assertEquals('generic', $framework);
    }

    /**
     * Test that Symfony is detected even when using symfony/symfony package.
     */
    public function testDetectFrameworkSymfonyPackage(): void
    {
        $this->createComposerJson(['symfony/symfony' => '^6.0']);
        $framework = $this->detectFrameworkForProject();
        $this->assertEquals('symfony', $framework);
    }

    /**
     * Test framework detection priority (first match wins).
     */
    public function testDetectFrameworkPriority(): void
    {
        // If both Symfony and Laravel are present, Symfony should be detected first
        // (based on the order in FRAMEWORK_PACKAGES constant)
        $this->createComposerJson([
            'symfony/framework-bundle' => '^6.0',
            'laravel/framework' => '^10.0',
        ]);
        $framework = $this->detectFrameworkForProject();
        $this->assertEquals('symfony', $framework);
    }

    /**
     * Helper method to create a composer.json file for testing.
     *
     * @param array<string, string> $require Packages to include in require section
     */
    private function createComposerJson(array $require): void
    {
        $composerData = [
            'name' => 'test/project',
            'require' => $require,
        ];

        file_put_contents($this->tempComposerJson, json_encode($composerData, JSON_PRETTY_PRINT));
    }

    /**
     * Helper method to detect framework using reflection to access private method.
     *
     * @return string The detected framework name
     */
    private function detectFrameworkForProject(): string
    {
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $config = $this->createMock(Config::class);
        $io = $this->createMock(IOInterface::class);

        // Mock vendor-dir to point to our temp directory
        $config->method('get')
            ->with('vendor-dir')
            ->willReturn($this->tempDir . '/vendor');

        $composer->method('getConfig')
            ->willReturn($config);

        $plugin->activate($composer, $io);

        // Use reflection to call the private detectFramework method
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('detectFramework');
        $method->setAccessible(true);

        return $method->invoke($plugin);
    }
}

