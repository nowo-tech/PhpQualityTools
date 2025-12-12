<?php

/**
 * TWIG-CS-FIXER CONFIGURATION
 * ============================
 *
 * Pre-configured Twig-CS-Fixer setup for Twig templates.
 * Customizations are loaded from .twig-cs-fixer.custom.php
 *
 * COMMANDS:
 * - ./vendor/bin/twig-cs-fixer lint templates/  (check for issues)
 * - ./vendor/bin/twig-cs-fixer lint --fix templates/  (fix issues)
 *
 * @see https://github.com/VincentLanglet/Twig-CS-Fixer
 * @package nowo-tech/php-quality-tools
 */

declare(strict_types=1);

use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;
use TwigCsFixer\Ruleset\Ruleset;
use TwigCsFixer\Standard\TwigCsFixer;

// Load custom configuration
$customConfigPath = __DIR__ . '/.twig-cs-fixer.custom.php';
$custom = file_exists($customConfigPath) ? require $customConfigPath : [];

// Default values
$paths = $custom['paths'] ?? [__DIR__ . '/templates'];
$exclude = $custom['exclude'] ?? ['vendor', 'var', 'node_modules'];
$disabledRules = $custom['disabled_rules'] ?? [];

// Create finder
$finder = new Finder();
foreach ($paths as $path) {
    if (is_dir($path)) {
        $finder->in($path);
    }
}
foreach ($exclude as $excludePath) {
    $finder->exclude($excludePath);
}

// Create ruleset with standard rules
$ruleset = new Ruleset();
$ruleset->addStandard(new TwigCsFixer());

// Disable specific rules if configured
foreach ($disabledRules as $ruleClass) {
    if (class_exists($ruleClass)) {
        $ruleset->removeRule(new $ruleClass());
    }
}

// Create and return config
$config = new Config();
$config->setRuleset($ruleset);
$config->setFinder($finder);

return $config;

