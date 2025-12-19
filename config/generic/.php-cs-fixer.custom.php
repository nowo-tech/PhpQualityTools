<?php

/**
 * PHP-CS-FIXER CUSTOM CONFIGURATION
 * ==================================
 *
 * This file contains YOUR PROJECT-SPECIFIC customizations.
 * It is imported by .php-cs-fixer.php and NEVER overwritten by package updates.
 *
 * @see https://cs.symfony.com/doc/config.html
 */

return [
    /**
     * Paths to include
     */
    'paths' => [
        __DIR__ . '/src',
        // __DIR__ . '/tests',
        // __DIR__ . '/lib',
    ],

    /**
     * Paths to exclude
     */
    'exclude' => [
        'vendor',
        'var',
        'node_modules',
        // 'src/Legacy',
    ],

    /**
     * Additional rules to enable/disable
     * These will be merged with the base rules
     *
     * @see https://cs.symfony.com/doc/rules/index.html
     *
     * To use PHP Quality Tools custom fixers, add them in .php-cs-fixer.php
     * by registering CustomFixersSet::getFixers() and including CustomFixersSet::getRules()
     */
    'rules' => [
        // PHP Quality Tools custom fixers are automatically included
        // if registered in .php-cs-fixer.php

        // Enable method argument space for multiline (works with SplitLongConstructorParametersRector)
        'method_argument_space' => ['ensure_fully_multiline' => true],

        // Configure concat spacing
        'concat_space' => ['spacing' => 'one'],

        // Configure array syntax
        'array_syntax' => ['syntax' => 'short'],

        // Configure ordered imports
        'ordered_imports' => ['sort_algorithm' => 'alpha'],

        // Example: disable a specific rule if needed
        // 'no_unused_imports' => false,
    ],

    /**
     * Indentation settings
     */
    'indent' => '    ', // 4 spaces

    /**
     * Line ending
     */
    'line_ending' => PHP_EOL, // Unix LF

    /**
     * Cache file
     */
    'cache_file' => __DIR__ . '/.php-cs-fixer.cache',

    /**
     * Project custom fixers
     * Add your project-specific custom fixers here
     * These fixers will be registered in addition to the ones from nowo-tech/php-quality-tools
     */
    'project_custom_fixers' => [
        // Uncomment if you have a project-specific MultilineGroupedImportsFixer
        // \App\Fixer\Custom\MultilineGroupedImportsFixer::class,
    ],
];

