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
    ]);
