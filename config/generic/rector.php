<?php

/**
 * RECTOR CONFIGURATION
 * ====================
 *
 * Pre-configured Rector setup for PHP projects.
 * Customizations are loaded from rector.custom.php
 *
 * This file can be edited, but consider putting project-specific
 * changes in rector.custom.php to keep this file clean.
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
use Rector\Set\ValueObject\SetList;
use Rector\Set\ValueObject\LevelSetList;

// Load custom configuration
$customConfigPath = __DIR__ . '/rector.custom.php';
$custom = file_exists($customConfigPath) ? require $customConfigPath : [];

// Default values
$paths = $custom['paths'] ?? [__DIR__ . '/src'];
$skip = $custom['skip'] ?? [];
$rules = $custom['rules'] ?? [];
$phpVersion = $custom['php_version'] ?? 'php84';
$framework = $custom['framework'] ?? 'auto';
$symfonyVersion = $custom['symfony_version'] ?? 74;
$features = $custom['features'] ?? [];

return RectorConfig::configure()
    /**
     * ========================================
     * PATHS TO PROCESS
     * ========================================
     */
    ->withPaths($paths)

    /**
     * ========================================
     * FILES/RULES TO SKIP
     * ========================================
     */
    ->withSkip($skip)

    /**
     * ========================================
     * INDENTATION
     * ========================================
     */
    ->withIndent(indentChar: ' ', indentSize: 4)

    /**
     * ========================================
     * PARALLEL PROCESSING
     * ========================================
     */
    ->withParallel(
        timeoutSeconds: 300,
        maxNumberOfProcess: 8,
        jobSize: 20
    )

    /**
     * ========================================
     * FILE EXTENSIONS
     * ========================================
     */
    ->withFileExtensions(['php'])

    /**
     * ========================================
     * IMPORT CONFIGURATION
     * ========================================
     */
    ->withImportNames(
        importNames: true,
        importDocBlockNames: false,
        importShortClasses: true,
        removeUnusedImports: true
    )

    /**
     * ========================================
     * ROOT FILES
     * ========================================
     */
    ->withRootFiles()

    /**
     * ========================================
     * COMPOSER-BASED DETECTION
     * ========================================
     */
    ->withComposerBased(
        twig: true,
        doctrine: true,
        phpunit: true,
        symfony: $framework === 'symfony' || $framework === 'auto',
        netteUtils: false
    )

    /**
     * ========================================
     * ATTRIBUTES CONVERSION
     * ========================================
     */
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

    /**
     * ========================================
     * PREPARED SETS (Feature Flags)
     * ========================================
     */
    ->withPreparedSets(
        typeDeclarations: $features['type_declarations'] ?? true,
        privatization: $features['privatization'] ?? true,
        earlyReturn: $features['early_return'] ?? true,
        deadCode: $features['dead_code'] ?? true,
        naming: $features['naming'] ?? true,
        instanceOf: true,
        codeQuality: $features['code_quality'] ?? true,
        codingStyle: true,
        doctrineCodeQuality: true,
        phpunitCodeQuality: true,
        symfonyCodeQuality: $framework === 'symfony' || $framework === 'auto',
        rectorPreset: true,
        symfonyConfigs: $framework === 'symfony' || $framework === 'auto'
    )

    /**
     * ========================================
     * CUSTOM RULES
     * ========================================
     */
    ->withRules($rules)

    /**
     * ========================================
     * PHP VERSION
     * ========================================
     */
    ->withPhpSets(
        php84: $phpVersion === 'php84',
        php83: $phpVersion === 'php83',
        php82: $phpVersion === 'php82',
        php81: $phpVersion === 'php81',
        php80: $phpVersion === 'php80',
        php74: $phpVersion === 'php74'
    )

    /**
     * ========================================
     * SET LISTS
     * ========================================
     */
    ->withSets(array_filter([
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

        // Symfony sets (conditional)
        ($framework === 'symfony' || $framework === 'auto') ? \Rector\Symfony\Set\SymfonySetList::SYMFONY_CODE_QUALITY : null,
        ($framework === 'symfony' || $framework === 'auto') ? \Rector\Symfony\Set\SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION : null,
        ($framework === 'symfony' || $framework === 'auto') ? \Rector\Symfony\Set\SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES : null,

        // Doctrine sets
        \Rector\Doctrine\Set\DoctrineSetList::DOCTRINE_CODE_QUALITY,
    ]));

