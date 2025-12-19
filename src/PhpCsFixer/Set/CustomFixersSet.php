<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\PhpCsFixer\Set;

/**
 * Custom PHP-CS-Fixer Fixers Set
 *
 * This class provides a convenient way to include all custom PHP-CS-Fixer fixers
 * from PHP Quality Tools in your PHP-CS-Fixer configuration.
 *
 * Usage in .php-cs-fixer.php or .php-cs-fixer.custom.php:
 *
 * ```php
 * use NowoTech\PhpQualityTools\PhpCsFixer\Set\CustomFixersSet;
 *
 * $config = (new Config())
 *     ->registerCustomFixers(CustomFixersSet::getFixers())
 *     ->setRules(array_merge([
 *         // ... other rules
 *     ], CustomFixersSet::getRules()));
 * ```
 */
final class CustomFixersSet
{
    /**
     * Get all custom PHP-CS-Fixer fixer instances.
     *
     * @return array<\PhpCsFixer\Fixer\FixerInterface> Array of fixer instances
     */
    public static function getFixers(): array
    {
        $fixers = [];

        // Add custom fixers here as they are created
        $fixers[] = new \NowoTech\PhpQualityTools\PhpCsFixer\Rules\MultilineGroupedImportsFixer();
        $fixers[] = new \NowoTech\PhpQualityTools\PhpCsFixer\Rules\MultilineArrayFixer();
        $fixers[] = new \NowoTech\PhpQualityTools\PhpCsFixer\Rules\ConsistentDocblockFixer();

        return $fixers;
    }

    /**
     * Get rules configuration for custom fixers.
     *
     * @return array<string, bool|array> Array of rule configurations
     */
    public static function getRules(): array
    {
        $rules = [];

        // Add rule configurations here as fixers are created
        $rules['NowoTech/multiline_grouped_imports'] = true;
        $rules['NowoTech/multiline_array'] = true;
        $rules['NowoTech/consistent_docblock'] = true;

        return $rules;
    }

    /**
     * Check if custom fixers are available.
     *
     * @return bool True if any custom fixers are available
     */
    public static function hasFixers(): bool
    {
        return count(self::getFixers()) > 0;
    }
}

