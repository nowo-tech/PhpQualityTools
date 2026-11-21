<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class GoodConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('good');
        $root = $tree->getRootNode();
        $root->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->scalarNode('prefix')->defaultValue('nowo_')->end()
        ->end();

        return $tree;
    }
}
