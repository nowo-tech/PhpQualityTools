<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Twig;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** Breaks the contract: the bundle views come before the override directory, and the alias chain is not followed. */
final class WrongOrderTwigPathsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('twig.loader.native_filesystem')) {
            return;
        }
        $definition = $container->getDefinition('twig.loader.native_filesystem');
        $definition->addMethodCall('addPath', [GoodTwigPathsPass::views(), GoodTwigPathsPass::TWIG_NAMESPACE]);
        $projectDir = $container->getParameter('kernel.project_dir');
        $override = (\is_string($projectDir) ? $projectDir : '') . '/templates/bundles/' . GoodTwigPathsPass::TWIG_NAMESPACE;
        if (is_dir($override)) {
            $definition->addMethodCall('prependPath', [$override, GoodTwigPathsPass::TWIG_NAMESPACE]);
        }
    }
}
