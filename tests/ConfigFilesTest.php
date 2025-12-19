<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.com>
 *
 * @see    https://github.com/HecFranco
 */
class ConfigFilesTest extends TestCase
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
        $this->assertFileExists($this->configDir . '/generic/.php-cs-fixer.php');
        $this->assertFileExists($this->configDir . '/generic/.php-cs-fixer.custom.php');
        $this->assertFileExists($this->configDir . '/generic/.twig-cs-fixer.php');
        $this->assertFileExists($this->configDir . '/generic/.twig-cs-fixer.custom.php');
    }

    public function testSymfonyConfigFilesExist(): void
    {
        $this->assertFileExists($this->configDir . '/symfony/rector.php');
        $this->assertFileExists($this->configDir . '/symfony/rector.custom.php');
        $this->assertFileExists($this->configDir . '/symfony/.php-cs-fixer.php');
        $this->assertFileExists($this->configDir . '/symfony/.php-cs-fixer.custom.php');
        $this->assertFileExists($this->configDir . '/symfony/.twig-cs-fixer.php');
        $this->assertFileExists($this->configDir . '/symfony/.twig-cs-fixer.custom.php');
    }

    public function testLaravelConfigFilesExist(): void
    {
        $this->assertFileExists($this->configDir . '/laravel/rector.php');
        $this->assertFileExists($this->configDir . '/laravel/rector.custom.php');
        $this->assertFileExists($this->configDir . '/laravel/.php-cs-fixer.php');
        $this->assertFileExists($this->configDir . '/laravel/.php-cs-fixer.custom.php');
    }

    public function testRectorConfigFilesAreValidPhp(): void
    {
        $rectorFiles = [
            $this->configDir . '/generic/rector.php',
            $this->configDir . '/symfony/rector.php',
            $this->configDir . '/laravel/rector.php',
        ];

        foreach ($rectorFiles as $file) {
            $this->assertFileExists($file);
            $content = file_get_contents($file);
            $this->assertStringContainsString('RectorConfig', $content);
            $this->assertStringContainsString('declare(strict_types=1)', $content);
        }
    }

    public function testCustomConfigFilesReturnArray(): void
    {
        $customFiles = [
            $this->configDir . '/generic/rector.custom.php',
            $this->configDir . '/symfony/rector.custom.php',
            $this->configDir . '/laravel/rector.custom.php',
        ];

        foreach ($customFiles as $file) {
            $this->assertFileExists($file);
            $result = require $file;
            $this->assertIsArray($result, "File {$file} should return an array");
        }
    }

    public function testPhpCsFixerConfigFilesAreValidPhp(): void
    {
        $csfixerFiles = [
            $this->configDir . '/generic/.php-cs-fixer.php',
            $this->configDir . '/symfony/.php-cs-fixer.php',
            $this->configDir . '/laravel/.php-cs-fixer.php',
        ];

        foreach ($csfixerFiles as $file) {
            $this->assertFileExists($file);
            $content = file_get_contents($file);
            $this->assertStringContainsString('PhpCsFixer', $content);
            $this->assertStringContainsString('declare(strict_types=1)', $content);
        }
    }

    public function testTwigCsFixerConfigFilesExist(): void
    {
        // Twig-CS-Fixer config files exist in source for Symfony and Generic
        // (Laravel doesn't have them in source, but they can be installed at runtime if twig/twig is detected)
        $this->assertFileExists($this->configDir . '/generic/.twig-cs-fixer.php');
        $this->assertFileExists($this->configDir . '/generic/.twig-cs-fixer.custom.php');
        $this->assertFileExists($this->configDir . '/symfony/.twig-cs-fixer.php');
        $this->assertFileExists($this->configDir . '/symfony/.twig-cs-fixer.custom.php');
    }

    public function testTwigCsFixerConfigFilesAreValidPhp(): void
    {
        $twigFiles = [
            $this->configDir . '/generic/.twig-cs-fixer.php',
            $this->configDir . '/symfony/.twig-cs-fixer.php',
        ];

        foreach ($twigFiles as $file) {
            $this->assertFileExists($file);
            $content = file_get_contents($file);
            $this->assertStringContainsString('TwigCsFixer', $content);
            $this->assertStringContainsString('declare(strict_types=1)', $content);
        }
    }

    public function testCustomPhpCsFixerConfigFilesReturnArray(): void
    {
        $customFiles = [
            $this->configDir . '/generic/.php-cs-fixer.custom.php',
            $this->configDir . '/symfony/.php-cs-fixer.custom.php',
            $this->configDir . '/laravel/.php-cs-fixer.custom.php',
        ];

        foreach ($customFiles as $file) {
            $this->assertFileExists($file);
            $result = require $file;
            $this->assertIsArray($result, "File {$file} should return an array");
        }
    }

    public function testCustomTwigCsFixerConfigFilesReturnArray(): void
    {
        $customFiles = [
            $this->configDir . '/generic/.twig-cs-fixer.custom.php',
            $this->configDir . '/symfony/.twig-cs-fixer.custom.php',
        ];

        foreach ($customFiles as $file) {
            $this->assertFileExists($file);
            $result = require $file;
            $this->assertIsArray($result, "File {$file} should return an array");
        }
    }

    public function testLaravelDoesNotHaveTwigConfigInSource(): void
    {
        // Laravel source config should not have Twig-CS-Fixer config files
        // (Twig-CS-Fixer can be installed at runtime if twig/twig is detected)
        $this->assertFileDoesNotExist($this->configDir . '/laravel/.twig-cs-fixer.php');
        $this->assertFileDoesNotExist($this->configDir . '/laravel/.twig-cs-fixer.custom.php');
    }
}
