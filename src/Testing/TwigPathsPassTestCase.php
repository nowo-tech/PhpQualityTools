<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * The contract of a bundle's `TwigPathsPass` (the compiler pass that registers the bundle's Twig namespace):
 *
 * - without a native Twig loader it does nothing;
 * - the loader is found as `twig.loader.native` or `twig.loader.native_filesystem`, through any chain of aliases;
 * - the bundle views are added LAST with `addPath(<views>, <namespace>)`;
 * - the application override directory `<project>/templates/bundles/<namespace>` is prepended FIRST, only when it exists.
 *
 * ```php
 * #[CoversClass(TwigPathsPass::class)]
 * final class TwigPathsPassTest extends TwigPathsPassTestCase
 * {
 *     protected function passClass(): string { return TwigPathsPass::class; }
 *     protected function twigNamespace(): string { return 'NowoFooBundle'; }
 *     protected function viewsDirectory(): string { return dirname(__DIR__, 4) . '/src/Resources/views'; }
 * }
 * ```
 *
 * Needs `phpunit/phpunit` and `symfony/dependency-injection`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
abstract class TwigPathsPassTestCase extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    /**
     * @return class-string<CompilerPassInterface>
     */
    abstract protected function passClass(): string;

    /**
     * The Twig namespace the pass registers (also the name of the override directory under `templates/bundles/`).
     */
    abstract protected function twigNamespace(): string;

    /**
     * The absolute path of the bundle views the pass adds.
     */
    abstract protected function viewsDirectory(): string;

    /**
     * Container parameters a pass needs to be active (a feature flag, for instance), besides `kernel.project_dir`.
     *
     * @return array<string, mixed>
     */
    protected function parameters(): array
    {
        return [];
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
        // @igor-ignore - PHPUnit test-support code for the consumers' test suites, never a service of a worker application.
        $this->tempDirs = [];
        parent::tearDown();
    }

    public function testItDoesNothingWithoutANativeTwigLoader(): void
    {
        $container = $this->container($this->projectDir());

        $this->process($container);

        self::assertSame(['service_container'], array_keys($container->getDefinitions()));
    }

    public function testItAddsTheBundleViewsLastWhenThereIsNoOverrideDirectory(): void
    {
        $container = $this->container($this->projectDir());
        $loader = new Definition();
        $container->setDefinition('twig.loader.native_filesystem', $loader);

        $this->process($container);

        self::assertSame([['addPath', [$this->viewsDirectory(), $this->twigNamespace()]]], $loader->getMethodCalls());
    }

    public function testItPrependsTheOverrideDirectoryBeforeAddingTheBundleViewsWhenItExists(): void
    {
        $projectDir = $this->projectDir();
        $override = $projectDir . '/templates/bundles/' . $this->twigNamespace();
        self::assertTrue(mkdir($override, 0o777, true));
        $container = $this->container($projectDir);
        $loader = new Definition();
        $container->setDefinition('twig.loader.native_filesystem', $loader);

        $this->process($container);

        self::assertSame(
            [
                ['prependPath', [$override, $this->twigNamespace()]],
                ['addPath', [$this->viewsDirectory(), $this->twigNamespace()]],
            ],
            $loader->getMethodCalls(),
        );
    }

    public function testItUsesTheNativeLoaderDefinition(): void
    {
        $container = $this->container($this->projectDir());
        $loader = new Definition();
        $container->setDefinition('twig.loader.native', $loader);

        $this->process($container);

        self::assertSame([['addPath', [$this->viewsDirectory(), $this->twigNamespace()]]], $loader->getMethodCalls());
    }

    public function testItFollowsAChainOfAliasesToTheLoader(): void
    {
        $container = $this->container($this->projectDir());
        $loader = new Definition();
        $container->setDefinition('twig.loader.real', $loader);
        $container->setAlias('twig.loader.chain', 'twig.loader.real');
        $container->setAlias('twig.loader.native', 'twig.loader.chain');

        $this->process($container);

        self::assertSame([['addPath', [$this->viewsDirectory(), $this->twigNamespace()]]], $loader->getMethodCalls());
    }

    public function testItStillAddsTheBundleViewsWhenTheProjectDirectoryIsNotAString(): void
    {
        $container = $this->container($this->projectDir());
        $container->setParameter('kernel.project_dir', ['invalid']);
        $loader = new Definition();
        $container->setDefinition('twig.loader.native_filesystem', $loader);

        $this->process($container);

        self::assertSame([['addPath', [$this->viewsDirectory(), $this->twigNamespace()]]], $loader->getMethodCalls());
    }

    private function process(ContainerBuilder $container): void
    {
        $class = $this->passClass();
        (new $class())->process($container);
    }

    private function container(string $projectDir): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $projectDir);
        foreach ($this->parameters() as $name => $value) {
            $container->setParameter($name, $value);
        }

        return $container;
    }

    private function projectDir(): string
    {
        $dir = sys_get_temp_dir() . '/pqt-twig-paths-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($dir, 0o777, true));
        // @igor-ignore - PHPUnit test-support code for the consumers' test suites, never a service of a worker application.
        $this->tempDirs[] = $dir;

        return $dir;
    }

    private function removeDirectory(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        /** @var \SplFileInfo $info */
        foreach ($iterator as $info) {
            $info->isDir() ? rmdir($info->getPathname()) : unlink($info->getPathname());
        }
        rmdir($dir);
    }
}
