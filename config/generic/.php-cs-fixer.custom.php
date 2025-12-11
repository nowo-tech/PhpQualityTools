<?php

/**
 * PHP-CS-FIXER CUSTOM CONFIGURATION
 * ==================================
 *
 * This file contains YOUR PROJECT-SPECIFIC customizations.
 * It is imported by .php-cs-fixer.dist.php and NEVER overwritten by package updates.
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
     */
    'rules' => [
        // Example: disable a specific rule
        // 'no_unused_imports' => false,

        // Example: configure a rule
        // 'concat_space' => ['spacing' => 'one'],
    ],

    /**
     * Indentation settings
     */
    'indent' => '    ', // 4 spaces

    /**
     * Line ending
     */
    'line_ending' => "\n",
];

