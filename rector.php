<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\AddParamArrayDocblockFromDimFetchAccessRector;
use RectorLaravel\Set\LaravelSetProvider;

return RectorConfig::configure()
    ->withSetProviders(LaravelSetProvider::class)
    ->withComposerBased(laravel: true)
    ->withPaths([
        __DIR__.'/src',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        //        naming: true,
        instanceOf: true,
        earlyReturn: true,
        rectorPreset: true,
    )
    ->withPhpSets(php82: true)
    ->withImportNames(removeUnusedImports: true)
    // fromArray() methods use @phpstan-param with typed array shapes — this rule would add redundant @param array<string, mixed>
    ->withSkip([
        AddParamArrayDocblockFromDimFetchAccessRector::class,
    ]);
