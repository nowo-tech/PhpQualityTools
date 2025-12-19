<?php

/**
 * PHP-CS-FIXER CUSTOM CONFIGURATION - LARAVEL
 * ============================================
 *
 * This file contains YOUR PROJECT-SPECIFIC customizations for Laravel.
 * It is imported by .php-cs-fixer.php and NEVER overwritten by package updates.
 *
 * Note: PHP-CS-Fixer can format Laravel Blade templates (.blade.php files).
 * The main config includes .blade.php files in the finder.
 * Be aware that some Blade-specific syntax may need manual review after formatting.
 *
 * @see https://cs.symfony.com/doc/config.html
 */

return [
    /**
     * Paths to include
     * 
     * For Blade templates, include your views directory:
     * __DIR__ . '/resources/views',
     */
    'paths' => [
        __DIR__ . '/app',
        __DIR__ . '/tests',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        // Uncomment to include Blade templates:
        // __DIR__ . '/resources/views',
    ],

    /**
     * Paths to exclude
     */
    'exclude' => [
        'vendor',
        'storage',
        'bootstrap/cache',
        'node_modules',
    ],

    /**
     * Additional rules to enable/disable
     * 
     * PHP Quality Tools custom fixers are automatically registered in .php-cs-fixer.php
     * if the CustomFixersSet class is available.
     */
    'rules' => [
        // PHP Quality Tools custom fixers are automatically included
        // Enable method argument space for multiline (works with SplitLongConstructorParametersRector)
        'method_argument_space' => ['ensure_fully_multiline' => true],
        
        // Configure concat spacing
        'concat_space' => ['spacing' => 'one'],
        
        // Configure array syntax
        'array_syntax' => ['syntax' => 'short'],
        
        // Configure ordered imports
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        
        // Laravel specific overrides
        // 'not_operator_with_successor_space' => true,
    ],
];

