<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use NowoTech\PhpQualityTools\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @author Héctor Franco Aceituno <hectorfranco@nowo.com>
 *
 * @see    https://github.com/HecFranco
 */
class PluginTest extends TestCase
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
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $io = $this->createMock(IOInterface::class);

        $plugin->activate($composer, $io);

        // Verify that Composer and IO are stored using reflection
        $reflection = new ReflectionClass($plugin);

        $composerProperty = $reflection->getProperty('composer');
        $composerProperty->setAccessible(true);
        $this->assertSame($composer, $composerProperty->getValue($plugin));

        $ioProperty = $reflection->getProperty('io');
        $ioProperty->setAccessible(true);
        $this->assertSame($io, $ioProperty->getValue($plugin));
    }

    public function testUninstallPreservesFiles(): void
    {
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $io = $this->createMock(IOInterface::class);

        // Verify that uninstall writes the expected message
        $io->expects($this->once())
            ->method('write')
            ->with($this->stringContains('Configuration files preserved'));

        $plugin->uninstall($composer, $io);
    }

    public function testPluginImplementsRequiredInterfaces(): void
    {
        $plugin = new Plugin();

        $this->assertInstanceOf(PluginInterface::class, $plugin);
        $this->assertInstanceOf(EventSubscriberInterface::class, $plugin);
    }

    public function testGetSubscribedEventsReturnsCorrectMethods(): void
    {
        $events = Plugin::getSubscribedEvents();

        $this->assertIsString($events[ScriptEvents::POST_INSTALL_CMD]);
        $this->assertIsString($events[ScriptEvents::POST_UPDATE_CMD]);
        $this->assertTrue(method_exists(Plugin::class, $events[ScriptEvents::POST_INSTALL_CMD]));
        $this->assertTrue(method_exists(Plugin::class, $events[ScriptEvents::POST_UPDATE_CMD]));
    }

    public function testDeactivateDoesNothing(): void
    {
        $plugin = new Plugin();
        $composer = $this->createMock(Composer::class);
        $io = $this->createMock(IOInterface::class);

        // Deactivate should not throw any exception and should not call any methods
        $io->expects($this->never())->method('write');
        $io->expects($this->never())->method('writeError');

        $plugin->deactivate($composer, $io);

        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }
}
