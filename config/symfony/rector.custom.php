<?php

declare(strict_types=1);

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
    // Paths to analyze
    'paths' => [
        __DIR__ . '/src',
        __DIR__ . '/tests',
        // __DIR__ . '/lib',
        // __DIR__ . '/migrations',
    ],

    // Paths and rules to skip
    'skip' => [
        // Example: skip specific files
        // __DIR__ . '/src/Kernel.php',

        // Example: skip directories
        // __DIR__ . '/src/DataFixtures/*',

        // Example: skip specific rules
        // \Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector::class,
        // \Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector::class,
    ],

    // Additional rules to apply
    'rules' => [
        // \Rector\TypeDeclaration\Rector\ClassMethod\AddParamTypeDeclarationRector::class,
    ],

    /*
     * Symfony version
     * Options: 60, 61, 62, 63, 64, 70, 71, 72, 73, 74
     */
    'symfony_version' => 74,

    /*
     * PHP version target
     * Options: php74, php80, php81, php82, php83, php84
     */
    'php_version' => 'php84',

    // Enable/disable feature sets
    'features' => [
        'type_declarations' => true,
        'dead_code'         => true,
        'code_quality'      => true,
        'early_return'      => true,
        'naming'            => true,
        'privatization'     => true,
        'doctrine'          => true,
        'phpunit'           => true,
    ],

    // Indentation
    'indent_size' => 4,
];
