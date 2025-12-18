<?php

/**
 * RECTOR CUSTOM CONFIGURATION - LARAVEL
 * ======================================
 *
 * This file contains YOUR PROJECT-SPECIFIC customizations for Laravel.
 * It is imported by rector.php and NEVER overwritten by package updates.
 *
 * @see https://getrector.com/documentation
 */

return [
    /**
     * Paths to analyze
     */
    'paths' => [
        __DIR__ . '/app',
        __DIR__ . '/tests',
        // __DIR__ . '/database',
        // __DIR__ . '/routes',
    ],

    /**
     * Paths and rules to skip
     */
    'skip' => [
        // Example: skip specific files
        // __DIR__ . '/app/Providers/AppServiceProvider.php',

        // Example: skip directories
        // __DIR__ . '/app/Console/Commands/*',

        // Example: skip specific rules
        // \Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector::class,
    ],

    /**
     * Additional rules to apply
     * 
     * PHP Quality Tools custom rules are automatically included in rector.php
     * if the CustomRulesSet class is available.
     * 
     * To add your own custom rules, add them here:
     */
    'rules' => [
        // PHP Quality Tools custom rules are automatically included
        // Add your additional custom rules here:
        // \Rector\TypeDeclaration\Rector\ClassMethod\AddParamTypeDeclarationRector::class,
    ],

    /**
     * Laravel version
     * Options: 90, 100, 110
     */
    'laravel_version' => 110,

    /**
     * PHP version target
     * Options: php80, php81, php82, php83, php84, php85
     */
    'php_version' => 'php85',

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
        'phpunit' => true,
    ],

    /**
     * Indentation
     */
    'indent_size' => 4,
];

