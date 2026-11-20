<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Unit\Testing;

use NowoTech\PhpQualityTools\Testing\ConfigurationThreadStack;
use NowoTech\PhpQualityTools\Testing\ConfigurationThreadStackTestCase;
use NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Config\BrokenConfiguration;
use NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Config\GoodConfiguration;
use NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Config\RequiredKeyConfiguration;
use NowoTech\PhpQualityTools\Tests\Support\Testing\BrokenConfigurationThreadStackProbe;
use NowoTech\PhpQualityTools\Tests\Support\Testing\BrokenSourceThreadStackProbe;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The failing cases of ConfigurationThreadStackTestCase (the passing ones are GoodConfigurationThreadStackTest).
 */
#[CoversClass(ConfigurationThreadStackTestCase::class)]
#[CoversClass(ConfigurationThreadStack::class)]
final class ConfigurationThreadStackTestCaseTest extends TestCase
{
    public function testInspectReturnsDefaultsAndBothReferences(): void
    {
        $good = ConfigurationThreadStack::inspect(GoodConfiguration::class);

        self::assertStringContainsString("'enabled' => true", $good['defaults']);
        self::assertStringContainsString("'prefix' => 'nowo_'", $good['defaults']);
        self::assertStringContainsString('prefix:', $good['yaml']);
        self::assertStringContainsString('<config', $good['xml']);
    }

    public function testInspectKeepsTheMessageOfAnInvalidDefaultConfiguration(): void
    {
        $required = ConfigurationThreadStack::inspect(RequiredKeyConfiguration::class);

        self::assertStringContainsString('The child config "dsn" under "required" must be configured', $required['defaults']);
    }

    public function testTheProviderListsTheConfigurationClasses(): void
    {
        self::assertSame(['Good' => [GoodConfiguration::class], 'RequiredKey' => [RequiredKeyConfiguration::class]], GoodConfigurationThreadStackTest::configurations());
    }

    public function testAConfigurationThatDiesInTheChildFails(): void
    {
        $probe = new BrokenConfigurationThreadStackProbe('testTheConfigurationLoadsBuildsAndProcessesOnAFrankenPhpThreadStack');

        try {
            $probe->testTheConfigurationLoadsBuildsAndProcessesOnAFrankenPhpThreadStack(BrokenConfiguration::class);
            self::fail('The broken configuration was accepted.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('The tree cannot be built.', $e->getMessage());
        }
    }

    public function testASourceFileThatDoesNotCompileFails(): void
    {
        $dir = BrokenSourceThreadStackProbe::directory();
        mkdir($dir, 0o777, true);
        file_put_contents($dir . '/Broken.php', "<?php\nfunction (;\n");
        $probe = new BrokenSourceThreadStackProbe('testEverySourceFileCompilesOnAFrankenPhpThreadStack');

        try {
            $probe->testEverySourceFileCompilesOnAFrankenPhpThreadStack();
            self::fail('The file that does not compile was accepted.');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('Broken.php', $e->getMessage());
        } finally {
            unlink($dir . '/Broken.php');
            rmdir($dir);
        }
    }

    public function testADirectoryWithoutPhpFilesFails(): void
    {
        $dir = BrokenSourceThreadStackProbe::directory();
        mkdir($dir, 0o777, true);
        $probe = new BrokenSourceThreadStackProbe('testEverySourceFileCompilesOnAFrankenPhpThreadStack');

        try {
            $probe->testEverySourceFileCompilesOnAFrankenPhpThreadStack();
            self::fail('An empty source directory was accepted.');
        } catch (AssertionFailedError) {
            $this->addToAssertionCount(1);
        } finally {
            rmdir($dir);
        }
    }
}
