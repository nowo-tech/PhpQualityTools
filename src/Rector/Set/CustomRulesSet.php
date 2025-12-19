<?php

declare(strict_types=1);

namespace NowoTech\PhpQualityTools\Rector\Set;

/**
 * Custom Rector Rules Set
 *
 * This class provides a convenient way to include all custom Rector rules
 * from PHP Quality Tools in your Rector configuration.
 *
 * Usage in .rector.php or .rector.custom.php:
 *
 * ```php
 * use NowoTech\PhpQualityTools\Rector\Set\CustomRulesSet;
 *
 * return [
 *     'rules' => CustomRulesSet::getRules(),
 * ];
 * ```
 */
final class CustomRulesSet
{
    /**
     * Required dependencies for custom Rector rules.
     */
    private const REQUIRED_DEPENDENCIES = [
        'Symplify\\RuleDocGenerator\\ValueObject\\RuleDefinition' => [
            'package' => 'symplify/rule-doc-generator-contracts',
            'description' => 'Required for custom Rector rules documentation',
        ],
    ];

    /**
     * Get all custom Rector rules.
     *
     * @param bool $checkDependencies Whether to check and report missing dependencies
     *
     * @return array<string> Array of rule class names
     */
    public static function getRules(bool $checkDependencies = true): array
    {
        if ($checkDependencies) {
            $missing = self::checkDependencies();
            if (!empty($missing)) {
                self::reportMissingDependencies($missing);
            }
        }

        $rules = [];

        // Add custom rules here as they are created
        $rules[] = \NowoTech\PhpQualityTools\Rector\Rules\SplitLongGroupedImportsRector::class;
        $rules[] = \NowoTech\PhpQualityTools\Rector\Rules\SplitLongConstructorParametersRector::class;
        $rules[] = \NowoTech\PhpQualityTools\Rector\Rules\AddMissingReturnTypeRector::class;
        $rules[] = \NowoTech\PhpQualityTools\Rector\Rules\SplitLongMethodCallRector::class;
        // Note: RemoveUnusedUseStatementsRector is commented out as Rector already has
        // built-in functionality for this via removeUnusedImports configuration
        // $rules[] = \NowoTech\PhpQualityTools\Rector\Rules\RemoveUnusedUseStatementsRector::class;

        return $rules;
    }

    /**
     * Check if all required dependencies are available.
     *
     * @return array<string, array{package: string, description: string}> Missing dependencies
     */
    public static function checkDependencies(): array
    {
        $missing = [];

        foreach (self::REQUIRED_DEPENDENCIES as $class => $info) {
            if (!class_exists($class) && !interface_exists($class)) {
                $missing[$class] = $info;
            }
        }

        return $missing;
    }

    /**
     * Report missing dependencies.
     *
     * @param array<string, array{package: string, description: string}> $missing Missing dependencies
     */
    private static function reportMissingDependencies(array $missing): void
    {
        $message = "\n";
        $message .= "⚠️  PHP Quality Tools - Missing dependencies for custom Rector rules:\n";
        $message .= "\n";

        $packages = [];
        foreach ($missing as $class => $info) {
            $message .= sprintf("  - %s: %s\n", $info['package'], $info['description']);
            $packages[] = $info['package'];
        }

        $message .= "\n";
        $message .= "To install missing dependencies, run:\n";
        $message .= sprintf("  composer require --dev %s\n", implode(' ', array_unique($packages)));
        $message .= "\n";
        $message .= "Note: Custom Rector rules will not work correctly without these dependencies.\n";

        // Use error_log for CLI or trigger_error for web
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, $message);
        } else {
            trigger_error($message, E_USER_WARNING);
        }
    }

    /**
     * Check if custom rules are available.
     *
     * @return bool True if any custom rules are available
     */
    public static function hasRules(): bool
    {
        return count(self::getRules(checkDependencies: false)) > 0;
    }

    /**
     * Check if all required dependencies are installed.
     *
     * @return bool True if all dependencies are available
     */
    public static function hasAllDependencies(): bool
    {
        return empty(self::checkDependencies());
    }

    /**
     * Get a list of missing dependencies with installation instructions.
     *
     * @return array<string> Array of package names that need to be installed
     */
    public static function getMissingDependencies(): array
    {
        $missing = self::checkDependencies();
        $packages = [];

        foreach ($missing as $info) {
            $packages[] = $info['package'];
        }

        return array_unique($packages);
    }
}
