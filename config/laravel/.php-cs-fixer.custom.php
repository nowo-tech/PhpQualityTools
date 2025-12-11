<?php

/**
 * PHP-CS-FIXER CUSTOM CONFIGURATION - LARAVEL
 * ============================================
 *
 * This file contains YOUR PROJECT-SPECIFIC customizations for Laravel.
 * It is imported by .php-cs-fixer.dist.php and NEVER overwritten by package updates.
 *
 * @see https://cs.symfony.com/doc/config.html
 */

return [
    /**
     * Paths to include
     */
    'paths' => [
        __DIR__ . '/app',
        __DIR__ . '/tests',
        __DIR__ . '/database',
        __DIR__ . '/routes',
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
     */
    'rules' => [
        // Laravel specific overrides
        // 'not_operator_with_successor_space' => true,
    ],
];

