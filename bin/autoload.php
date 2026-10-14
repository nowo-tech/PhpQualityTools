<?php

declare(strict_types=1);

/*
 * Locates the Composer autoloader for the bin/ scripts: the host project's (installed as a
 * dependency, via vendor/bin proxies) or this package's own (development checkout).
 * Without either (a bare `git clone` of this package, e.g. in a deploy guard job) it falls back to a
 * PSR-4 loader for src/: bin/assert-latest-tag and bin/check-coverage-threshold need no dependency.
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
    $src = __DIR__ . '/../src/';
    if (!is_dir($src)) {
        fwrite(\STDERR, "ERROR: Composer autoloader not found; run composer install.\n");
        exit(2);
    }
    spl_autoload_register(static function (string $class) use ($src): void {
        $prefix = 'NowoTech\\PhpQualityTools\\';
        if (str_starts_with($class, $prefix)) {
            $file = $src . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
})();
