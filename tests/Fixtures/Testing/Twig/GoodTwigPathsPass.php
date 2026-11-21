<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Twig;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** The reference behaviour of a bundle's TwigPathsPass. */
final class GoodTwigPathsPass implements CompilerPassInterface
{
    public const TWIG_NAMESPACE = 'NowoFixtureBundle';

    public static function views(): string
    {
        return __DIR__ . '/views';
    }

    public function process(ContainerBuilder $container): void
    {
        $loaderId = $this->nativeLoaderId($container);
        if (null === $loaderId) {
            return;
        }

        $definition = $container->getDefinition($loaderId);
        $projectDir = $container->hasParameter('kernel.project_dir') ? $container->getParameter('kernel.project_dir') : null;
        if (\is_string($projectDir)) {
            $overridePath = rtrim($projectDir, '/\\') . '/templates/bundles/' . self::TWIG_NAMESPACE;
            if (is_dir($overridePath)) {
                $definition->addMethodCall('prependPath', [$overridePath, self::TWIG_NAMESPACE]);
            }
        }

        $definition->addMethodCall('addPath', [self::views(), self::TWIG_NAMESPACE]);
    }

    private function nativeLoaderId(ContainerBuilder $container): ?string
    {
        foreach (['twig.loader.native', 'twig.loader.native_filesystem'] as $id) {
            for ($i = 0; $i < 32 && $container->hasAlias($id); ++$i) {
                $id = (string) $container->getAlias($id);
            }
            if ($container->hasDefinition($id)) {
                return $id;
            }
        }

        return null;
    }
}
