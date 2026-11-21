<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Tests\Fixtures\Testing\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/** Cannot even build its tree: the child process dies. */
final class BrokenConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        throw new \LogicException('The tree cannot be built.');
    }
}
