<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Testing;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Dumper\XmlReferenceDumper;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * What the child process of {@see ConfigurationThreadStackTestCase} computes about a configuration class, and what the
 * parent compares it with: the processed defaults (`var_export`) and the YAML and XML references.
 *
 * Needs `symfony/config`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @see    https://github.com/HecFranco
 */
final class ConfigurationThreadStack
{
    /**
     * Builds the tree, processes the defaults and dumps both references.
     *
     * A required key without a default makes the processing fail: the message is then part of the comparison.
     *
     * @param class-string<ConfigurationInterface> $class
     *
     * @return array{defaults: string, yaml: string, xml: string}
     */
    public static function inspect(string $class): array
    {
        $configuration = new $class();

        try {
            $defaults = var_export((new Processor())->processConfiguration($configuration, [[]]), true);
        } catch (InvalidConfigurationException $e) {
            $defaults = $e->getMessage();
        }

        return [
            'defaults' => $defaults,
            'yaml' => (new YamlReferenceDumper())->dump($configuration),
            'xml' => (new XmlReferenceDumper())->dump($configuration),
        ];
    }
}
