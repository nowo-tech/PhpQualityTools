<?php

/**
 * RECTOR CUSTOM CONFIGURATION - SYMFONY
 * ======================================
 *
 * This file contains YOUR PROJECT-SPECIFIC customizations for Symfony.
 * It is imported by rector.php and NEVER overwritten by package updates.
 *
 * @see https://getrector.com/documentation
 */

return [
    /**
     * Paths to analyze
     */
    'paths' => [
        __DIR__ . '/src',
        __DIR__ . '/tests',
        // __DIR__ . '/lib',
        // __DIR__ . '/migrations',
    ],

    /**
     * Paths and rules to skip
     */
    'skip' => [
        // Example: skip specific files
        // __DIR__ . '/src/Kernel.php',

        // Example: skip directories
        // __DIR__ . '/src/DataFixtures/*',
        // __DIR__ . '/src/Migrations/*',

        // Example: skip specific rules for certain paths
        // \Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector::class => [
        //     __DIR__ . '/src/Legacy',
        // ],

        // Example: skip specific rules globally
        // \Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector::class,
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
     * Symfony version
     * Options: 60, 61, 62, 63, 64, 70, 71, 72, 73, 74
     */
    'symfony_version' => 74,

    /**
     * PHP version target
     * Options: php74, php80, php81, php82, php83, php84, php85
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
        'doctrine' => true,
        'phpunit' => true,
    ],

    /**
     * Indentation
     */
    'indent_size' => 4,
];

