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
        return [new \NowoTech\PhpQualityTools\PhpCsFixer\Rules\MultilineGroupedImportsFixer(), new \NowoTech\PhpQualityTools\PhpCsFixer\Rules\MultilineArrayFixer(), new \NowoTech\PhpQualityTools\PhpCsFixer\Rules\ConsistentDocblockFixer()];
    }

    /**
     * Get rules configuration for custom fixers.
     *
     * @return array<string, bool> Array of rule configurations
     */
    public static function getRules(): array
    {
        return ['NowoTech/multiline_grouped_imports' => true, 'NowoTech/multiline_array' => true, 'NowoTech/consistent_docblock' => true];
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
