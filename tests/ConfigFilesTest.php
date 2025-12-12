<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 *
 * @see    https://github.com/HecFranco
 */
final class ConfigFilesTest extends TestCase
{
    private string $configDir;

    protected function setUp(): void
    {
        $this->configDir = dirname(__DIR__) . '/config';
    }

    public function testGenericConfigFilesExist(): void
    {
        $this->assertFileExists($this->configDir . '/generic/rector.php');
        $this->assertFileExists($this->configDir . '/generic/rector.custom.php');
        $this->assertFileExists($this->configDir . '/generic/.php-cs-fixer.dist.php');
        $this->assertFileExists($this->configDir . '/generic/.php-cs-fixer.custom.php');
        $this->assertFileExists($this->configDir . '/generic/.twig-cs-fixer.php');
        $this->assertFileExists($this->configDir . '/generic/.twig-cs-fixer.custom.php');
    }

    public function testSymfonyConfigFilesExist(): void
    {
        $this->assertFileExists($this->configDir . '/symfony/rector.php');
        $this->assertFileExists($this->configDir . '/symfony/rector.custom.php');
        $this->assertFileExists($this->configDir . '/symfony/.php-cs-fixer.dist.php');
        $this->assertFileExists($this->configDir . '/symfony/.php-cs-fixer.custom.php');
        $this->assertFileExists($this->configDir . '/symfony/.twig-cs-fixer.php');
        $this->assertFileExists($this->configDir . '/symfony/.twig-cs-fixer.custom.php');
    }

    public function testLaravelConfigFilesExist(): void
    {
        $this->assertFileExists($this->configDir . '/laravel/rector.php');
        $this->assertFileExists($this->configDir . '/laravel/rector.custom.php');
        $this->assertFileExists($this->configDir . '/laravel/.php-cs-fixer.dist.php');
        $this->assertFileExists($this->configDir . '/laravel/.php-cs-fixer.custom.php');
    }

    public function testRectorConfigFilesAreValidPhp(): void
    {
        $rectorFiles = [
            $this->configDir . '/generic/rector.php',
            $this->configDir . '/symfony/rector.php',
            $this->configDir . '/laravel/rector.php',
        ];

        foreach ($rectorFiles as $file)
        {
            $this->assertFileExists($file);
            $content = file_get_contents($file);
            $this->assertStringContainsString('RectorConfig', (string) $content);
            $this->assertStringContainsString('declare(strict_types=1)', (string) $content);
        }
    }

    public function testCustomConfigFilesReturnArray(): void
    {
        $customFiles = [
            $this->configDir . '/generic/rector.custom.php',
            $this->configDir . '/symfony/rector.custom.php',
            $this->configDir . '/laravel/rector.custom.php',
        ];

        foreach ($customFiles as $file)
        {
            $this->assertFileExists($file);
            $result = require $file;
            $this->assertIsArray($result, sprintf('File %s should return an array', $file));
        }
    }

    public function testPhpCsFixerConfigFilesAreValidPhp(): void
    {
        $csfixerFiles = [
            $this->configDir . '/generic/.php-cs-fixer.dist.php',
            $this->configDir . '/symfony/.php-cs-fixer.dist.php',
            $this->configDir . '/laravel/.php-cs-fixer.dist.php',
        ];

        foreach ($csfixerFiles as $file)
        {
            $this->assertFileExists($file);
            $content = file_get_contents($file);
            $this->assertStringContainsString('PhpCsFixer', (string) $content);
            $this->assertStringContainsString('declare(strict_types=1)', (string) $content);
        }
    }
}
