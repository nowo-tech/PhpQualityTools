<?php

/**
 * RECTOR CUSTOM CONFIGURATION
 * ===========================
 *
 * This file contains YOUR PROJECT-SPECIFIC customizations.
 * It is imported by rector.php and NEVER overwritten by package updates.
 *
 * Edit this file to customize:
 * - Paths to analyze
 * - Files/rules to skip
 * - Project-specific rules
 *
 * @see https://getrector.com/documentation
 */

return [
    /**
     * Paths to analyze
     * Add your project directories here
     */
    'paths' => [
        __DIR__ . '/src',
        // __DIR__ . '/tests',
        // __DIR__ . '/lib',
        // __DIR__ . '/migrations',
    ],

    /**
     * Paths and rules to skip
     * Add files, directories, or rule classes to ignore
     */
    'skip' => [
        // Example: skip specific files
        // __DIR__ . '/src/Legacy/OldClass.php',

        // Example: skip directories
        // __DIR__ . '/src/Generated/*',

        // Example: skip specific rules
        // \Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector::class,
    ],

    /**
     * Additional rules to apply
     * Add custom rules specific to your project
     */
    'rules' => [
        // Example: add specific rules
        // \Rector\TypeDeclaration\Rector\ClassMethod\AddParamTypeDeclarationRector::class,
    ],

    /**
     * PHP version target
     * Options: php74, php80, php81, php82, php83, php84
     */
    'php_version' => 'php84',

    /**
     * Framework detection
     * Set to 'symfony', 'laravel', or 'auto' for automatic detection
     */
    'framework' => 'auto',

    /**
     * Symfony version (only used if framework is 'symfony')
     * Options: 60, 61, 62, 63, 64, 70, 71, 72, 73, 74
     */
    'symfony_version' => 74,

    /**
     * Enable/disable feature sets
     */
    'features' => [
        'type_declarations' => true,
        'dead_code' => true,
        'code_quality' => true,
        'early_return' => true,
        'naming' => true,
        'privatization' => true,
    ],
];

