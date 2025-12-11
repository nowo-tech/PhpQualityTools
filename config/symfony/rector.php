<?php

/**
 * RECTOR CONFIGURATION - SYMFONY
 * ===============================
 *
 * Pre-configured Rector setup for Symfony projects.
 * Customizations are loaded from rector.custom.php
 *
 * COMMANDS:
 * - ./vendor/bin/rector process --dry-run  (preview changes)
 * - ./vendor/bin/rector process            (apply changes)
 *
 * @see https://getrector.com/documentation
 * @package nowo-tech/php-quality-tools
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;

// Load custom configuration
$customConfigPath = __DIR__ . '/rector.custom.php';
$custom = file_exists($customConfigPath) ? require $customConfigPath : [];

// Default values
$paths = $custom['paths'] ?? [__DIR__ . '/src'];
$skip = $custom['skip'] ?? [];
$rules = $custom['rules'] ?? [];
$phpVersion = $custom['php_version'] ?? 'php84';
$symfonyVersion = $custom['symfony_version'] ?? 74;
$features = $custom['features'] ?? [];
$indentSize = $custom['indent_size'] ?? 4;

// Build Symfony set name
$symfonySetName = 'SYMFONY_' . $symfonyVersion;

return RectorConfig::configure()
    // Paths
    ->withPaths($paths)

    // Skip
    ->withSkip($skip)

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
        twig: true,
        doctrine: $features['doctrine'] ?? true,
        phpunit: $features['phpunit'] ?? true,
        symfony: true,
        netteUtils: false
    )

    // Attributes conversion
    ->withAttributesSets(
        symfony: true,
        doctrine: true,
        mongoDb: false,
        gedmo: true,
        phpunit: true,
        fosRest: false,
        jms: false,
        sensiolabs: true,
        behat: false
    )

    // Prepared sets
    ->withPreparedSets(
        typeDeclarations: $features['type_declarations'] ?? true,
        privatization: $features['privatization'] ?? true,
        earlyReturn: $features['early_return'] ?? true,
        deadCode: $features['dead_code'] ?? true,
        naming: $features['naming'] ?? true,
        instanceOf: true,
        codeQuality: $features['code_quality'] ?? true,
        codingStyle: true,
        doctrineCodeQuality: $features['doctrine'] ?? true,
        phpunitCodeQuality: $features['phpunit'] ?? true,
        symfonyCodeQuality: true,
        rectorPreset: true,
        symfonyConfigs: true
    )

    // Custom rules
    ->withRules($rules)

    // PHP version
    ->withPhpSets(
        php84: $phpVersion === 'php84',
        php83: $phpVersion === 'php83',
        php82: $phpVersion === 'php82',
        php81: $phpVersion === 'php81',
        php80: $phpVersion === 'php80',
        php74: $phpVersion === 'php74'
    )

    // Set lists
    ->withSets([
        // PHP Level
        match ($phpVersion) {
            'php84' => LevelSetList::UP_TO_PHP_84,
            'php83' => LevelSetList::UP_TO_PHP_83,
            'php82' => LevelSetList::UP_TO_PHP_82,
            'php81' => LevelSetList::UP_TO_PHP_81,
            'php80' => LevelSetList::UP_TO_PHP_80,
            default => LevelSetList::UP_TO_PHP_84,
        },

        // Base sets
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::CODING_STYLE,

        // Symfony sets
        constant(SymfonySetList::class . '::' . $symfonySetName),
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,

        // Doctrine sets
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
    ]);

