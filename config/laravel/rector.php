<?php

/**
 * RECTOR CONFIGURATION - LARAVEL
 * ===============================
 *
 * Pre-configured Rector setup for Laravel projects.
 * Customizations are loaded from rector.custom.php
 *
 * COMMANDS:
 * - ./vendor/bin/rector process --dry-run  (preview changes)
 * - ./vendor/bin/rector process            (apply changes)
 *
 * REQUIRED:
 * - composer require --dev rector/rector
 * - composer require --dev driftingly/rector-laravel
 *
 * @see https://getrector.com/documentation
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\{LevelSetList, SetList};
use RectorLaravel\Set\LaravelSetList;

// Load custom configuration
$customConfigPath = __DIR__ . '/rector.custom.php';
$custom           = file_exists($customConfigPath) ? require $customConfigPath : [];

// Default values
$paths          = $custom['paths']           ?? [__DIR__ . '/app'];
$skip           = $custom['skip']            ?? [];
$rules          = $custom['rules']           ?? [];
$phpVersion     = $custom['php_version']     ?? 'php83';
$laravelVersion = $custom['laravel_version'] ?? 110;
$features       = $custom['features']        ?? [];
$indentSize     = $custom['indent_size']     ?? 4;

// Check if Laravel Rector is available
$hasLaravelRector = class_exists('RectorLaravel\Set\LaravelSetList');

return RectorConfig::configure()
    // Paths
    ->withPaths($paths)

    // Skip
    ->withSkip(array_merge($skip, [
        // Laravel specific skips
        __DIR__ . '/bootstrap/cache/*',
        __DIR__ . '/storage/*',
        __DIR__ . '/vendor/*',
    ]))

    // Indentation
    ->withIndent(indentChar: ' ', indentSize: $indentSize)

    // Parallel processing
    ->withParallel(
      timeoutSeconds: 300,
      maxNumberOfProcess: 8,
      jobSize: 20
    )

    // File extensions
    ->withFileExtensions(['php'])

    // Import configuration
    ->withImportNames(
      importNames: true,
      importDocBlockNames: false,
      importShortClasses: true,
      removeUnusedImports: true
    )

    // Root files
    ->withRootFiles()

    // Composer-based detection
    ->withComposerBased(
      twig: false,
      doctrine: false,
      phpunit: $features['phpunit'] ?? true,
      symfony: false,
      netteUtils: false
    )

    // Prepared sets
    ->withPreparedSets(
      deadCode: $features['dead_code']       ?? true,
      codeQuality: $features['code_quality'] ?? true,
      codingStyle: true,
      typeDeclarations: $features['type_declarations'] ?? true,
      privatization: $features['privatization']        ?? true,
      naming: $features['naming']                      ?? true,
      instanceOf: true,
      earlyReturn: $features['early_return'] ?? true,
      rectorPreset: true,
      phpunitCodeQuality: $features['phpunit'] ?? true,
      doctrineCodeQuality: false,
      symfonyCodeQuality: false,
      symfonyConfigs: false
    )

    // Custom rules
    ->withRules($rules)

    // PHP version
    ->withPhpSets(
      php83: $phpVersion === 'php83',
      php82: $phpVersion === 'php82',
      php81: $phpVersion === 'php81',
      php80: $phpVersion === 'php80',
      php84: $phpVersion === 'php84'
    )

    // Set lists
    ->withSets(array_filter([
        // PHP Level
        match ($phpVersion)
        {
            'php84' => LevelSetList::UP_TO_PHP_84,
            'php83' => LevelSetList::UP_TO_PHP_83,
            'php82' => LevelSetList::UP_TO_PHP_82,
            'php81' => LevelSetList::UP_TO_PHP_81,
            'php80' => LevelSetList::UP_TO_PHP_80,
            default => LevelSetList::UP_TO_PHP_83,
        },

        // Base sets
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::CODING_STYLE,

        // Laravel sets (if available)
        $hasLaravelRector ? LaravelSetList::LARAVEL_110 : null,
        $hasLaravelRector ? LaravelSetList::LARAVEL_CODE_QUALITY : null,
    ]));
