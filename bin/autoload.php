<?php

declare(strict_types=1);

/*
 * Locates the Composer autoloader for the bin/ scripts: the host project's (installed as a
 * dependency, via vendor/bin proxies) or this package's own (development checkout).
 */

(static function (): void {
    $candidates = [
        $GLOBALS['_composer_autoload_path'] ?? null,
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../../autoload.php',
    ];
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && is_file($candidate)) {
            require_once $candidate;

            return;
        }
    }
    fwrite(\STDERR, "ERROR: Composer autoloader not found; run composer install.\n");
    exit(2);
})();
