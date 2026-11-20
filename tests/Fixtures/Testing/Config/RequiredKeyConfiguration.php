<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/** A required key without a default: processing the defaults fails, and the message is part of the comparison. */
final class RequiredKeyConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('required');
        $tree->getRootNode()->children()->scalarNode('dsn')->isRequired()->cannotBeEmpty()->end()->end();

        return $tree;
    }
}
