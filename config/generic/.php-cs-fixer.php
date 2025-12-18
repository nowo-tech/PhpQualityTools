<?php

/**
 * PHP-CS-FIXER CONFIGURATION
 * ==========================
 *
 * Pre-configured PHP-CS-Fixer setup following PSR-12 and Symfony standards.
 * Customizations are loaded from .php-cs-fixer.custom.php
 *
 * COMMANDS:
 * - ./vendor/bin/php-cs-fixer fix --dry-run --diff  (preview changes)
 * - ./vendor/bin/php-cs-fixer fix                   (apply changes)
 *
 * @see https://cs.symfony.com/
 * @package nowo-tech/php-quality-tools
 */

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

// Load custom configuration
$customConfigPath = __DIR__ . '/.php-cs-fixer.custom.php';
$custom = file_exists($customConfigPath) ? require $customConfigPath : [];

// Default values
$paths = $custom['paths'] ?? [__DIR__ . '/src'];
$exclude = $custom['exclude'] ?? ['vendor', 'var', 'node_modules'];
$customRules = $custom['rules'] ?? [];

// Load PHP Quality Tools custom fixers (if available)
$phpQualityToolsFixers = [];
$phpQualityToolsRules = [];
if (class_exists(\NowoTech\PhpQualityTools\PhpCsFixer\Set\CustomFixersSet::class)) {
    try {
        $phpQualityToolsFixers = \NowoTech\PhpQualityTools\PhpCsFixer\Set\CustomFixersSet::getFixers();
        $phpQualityToolsRules = \NowoTech\PhpQualityTools\PhpCsFixer\Set\CustomFixersSet::getRules();
    } catch (\Throwable $e) {
        // Silently ignore if dependencies are missing
    }
}

// Create finder
$finder = Finder::create()
    ->in($paths)
    ->exclude($exclude)
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

// Base rules (PSR-12 + Symfony style)
$baseRules = [
    '@PSR12' => true,
    '@Symfony' => true,
    '@Symfony:risky' => false,
    '@PHP81Migration' => true,

    // Array syntax
    'array_syntax' => ['syntax' => 'short'],
    'array_indentation' => true,
    'trim_array_spaces' => true,
    'no_whitespace_before_comma_in_array' => true,
    'whitespace_after_comma_in_array' => true,
    'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],

    // Imports
    'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
    'no_unused_imports' => true,
    'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
    'single_import_per_statement' => true,

    // Spacing
    'binary_operator_spaces' => ['default' => 'single_space'],
    'concat_space' => ['spacing' => 'one'],
    'unary_operator_spaces' => true,
    'cast_spaces' => ['space' => 'single'],
    'type_declaration_spaces' => true,
    'no_extra_blank_lines' => [
        'tokens' => [
            'curly_brace_block',
            'extra',
            'parenthesis_brace_block',
            'square_brace_block',
            'throw',
            'use',
        ],
    ],

    // Blank lines
    'blank_line_before_statement' => [
        'statements' => ['return', 'throw', 'try', 'if', 'switch', 'for', 'foreach', 'while', 'do'],
    ],
    'no_blank_lines_after_class_opening' => true,
    'no_blank_lines_after_phpdoc' => true,
    'single_blank_line_before_namespace' => true,

    // Comments & PHPDoc
    'phpdoc_align' => ['align' => 'left'],
    'phpdoc_indent' => true,
    'phpdoc_order' => true,
    'phpdoc_scalar' => true,
    'phpdoc_separation' => true,
    'phpdoc_single_line_var_spacing' => true,
    'phpdoc_trim' => true,
    'phpdoc_types' => true,
    'phpdoc_var_without_name' => true,
    'no_empty_phpdoc' => true,
    'no_superfluous_phpdoc_tags' => ['allow_mixed' => true, 'remove_inheritdoc' => false],
    'single_line_comment_style' => ['comment_types' => ['hash']],

    // Class & method
    'class_attributes_separation' => ['elements' => ['method' => 'one', 'property' => 'one']],
    'class_definition' => ['single_line' => true],
    'self_accessor' => true,
    'single_class_element_per_statement' => true,
    'visibility_required' => ['elements' => ['property', 'method', 'const']],
    'ordered_class_elements' => [
        'order' => [
            'use_trait',
            'case',
            'constant_public',
            'constant_protected',
            'constant_private',
            'property_public',
            'property_protected',
            'property_private',
            'construct',
            'destruct',
            'magic',
            'phpunit',
            'method_public',
            'method_protected',
            'method_private',
        ],
    ],

    // Control structures
    'no_alternative_syntax' => true,
    'no_useless_else' => true,
    'no_useless_return' => true,
    'simplified_if_return' => true,
    'yoda_style' => false,

    // Strings
    'single_quote' => true,
    'explicit_string_variable' => true,

    // Semicolon
    'no_empty_statement' => true,
    'no_singleline_whitespace_before_semicolons' => true,
    'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],

    // Other
    'declare_strict_types' => true,
    'strict_param' => false,
    'native_function_invocation' => false,
    'modernize_types_casting' => true,
    'no_short_bool_cast' => true,
];

// Merge with custom rules: PHP Quality Tools rules first, then base rules, then user custom rules
$rules = array_merge($phpQualityToolsRules, $baseRules, $customRules);

$config = (new Config())
    ->setRules($rules)
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/var/cache/.php-cs-fixer.cache');

// Register PHP Quality Tools custom fixers if available
if (!empty($phpQualityToolsFixers)) {
    $config->registerCustomFixers($phpQualityToolsFixers);
}

return $config;

