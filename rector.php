<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // Machine-wide cap: at most 2 worker processes; generous timeout for slow (WSL2 / CI) hosts.
    ->withParallel(300, 2, 10)
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true
    )
    // Conflicts with php-cs-fixer fully_qualified_strict_types (short Tokens<Token> vs FQCN).
    ->withSkip([
        Rector\TypeDeclaration\Rector\FunctionLike\AddParamTypeSplFixedArrayRector::class,
        // `x || self::fail(...)` -> `if (!x) { $this->fail(...); }` adds statements that only run when the helper
        // process fails to start; this repo requires 100% coverage (make test-coverage-100) and the test helper is
        // not worth a failure-path test of its own.
        Rector\CodeQuality\Rector\Expression\InlineIfToExplicitIfRector::class => [
            __DIR__ . '/src/Testing/Concurrency/SecondConnection.php',
        ],
    ]);
