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
     * PHP Quality Tools custom fixers are automatically registered in .php-cs-fixer.php
     * if the CustomFixersSet class is available.
     */
    'rules' => [
        // PHP Quality Tools custom fixers are automatically included
        // Enable method argument space for multiline (works with SplitLongConstructorParametersRector)
        'method_argument_space' => ['ensure_fully_multiline' => true],
        
        // Example: configure concat spacing
        'concat_space' => ['spacing' => 'one'],
        
        // Example: configure array syntax
        'array_syntax' => ['syntax' => 'short'],
        
        // Example: configure ordered imports
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
    'line_ending' => "\n",
];

