<?php

/**
 * TWIG-CS-FIXER CUSTOM CONFIGURATION
 * ===================================
 *
 * This file contains YOUR PROJECT-SPECIFIC customizations.
 * It is imported by .twig-cs-fixer.php and NEVER overwritten by package updates.
 *
 * @see https://github.com/VincentLangworski/Twig-CS-Fixer
 */

return [
    /**
     * Paths to Twig templates
     */
    'paths' => [
        __DIR__ . '/templates',
        // __DIR__ . '/src/Resources/views',
    ],

    /**
     * Paths to exclude
     */
    'exclude' => [
        'vendor',
        'var',
        'node_modules',
    ],

    /**
     * Rules to disable
     * Add rule class names to disable specific rules
     */
    'disabled_rules' => [
        // Example: disable specific rules
        // \TwigCsFixer\Rules\Punctuation\PunctuationSpacingRule::class,
    ],

    /**
     * Custom rule configurations
     */
    'rule_config' => [
        // Example: configure indent
        // 'indent' => 4,
    ],
];

