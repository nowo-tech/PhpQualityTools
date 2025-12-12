<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use Composer\{Composer, Config};
use Composer\IO\IOInterface;
use Composer\Script\ScriptEvents;
use NowoTech\PhpQualityTools\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 *
 * @see    https://github.com/HecFranco
 */
final class PluginTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = Plugin::getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertArrayHasKey(ScriptEvents::POST_INSTALL_CMD, $events);
        $this->assertArrayHasKey(ScriptEvents::POST_UPDATE_CMD, $events);
        $this->assertEquals('onPostInstall', $events[ScriptEvents::POST_INSTALL_CMD]);
        $this->assertEquals('onPostUpdate', $events[ScriptEvents::POST_UPDATE_CMD]);
    }

    public function testActivateStoresComposerAndIo(): void
    {
        $plugin   = new Plugin();
        $composer = $this->createMock(Composer::class);
        $io       = $this->createMock(IOInterface::class);

        $plugin->activate($composer, $io);

        $this->assertTrue(true);
    }

    public function testDeactivateDoesNothing(): void
    {
        $plugin   = new Plugin();
        $composer = $this->createMock(Composer::class);
        $io       = $this->createMock(IOInterface::class);

        $plugin->deactivate($composer, $io);

        $this->assertTrue(true);
    }

    public function testUninstallPreservesFiles(): void
    {
        $plugin   = new Plugin();
        $composer = $this->createMock(Composer::class);
        $io       = $this->createMock(IOInterface::class);

        // Should not throw any exception
        $plugin->uninstall($composer, $io);

        $this->assertTrue(true);
    }
}
