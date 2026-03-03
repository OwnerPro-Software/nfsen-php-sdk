<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclarationDocblocks\Rector\Class_\ClassMethodArrayDocblockParamFromLocalCallsRector;
use Rector\TypeDeclarationDocblocks\Rector\ClassMethod\AddParamArrayDocblockFromDimFetchAccessRector;
use RectorLaravel\Rector\MethodCall\ContainerBindConcreteWithClosureOnlyRector;
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
    ->withSkip([
        // fromArray() methods use @phpstan-param with typed array shapes — this rule would add redundant @param array<string, mixed>
        AddParamArrayDocblockFromDimFetchAccessRector::class,
        // Bug no Rector 2.3.8: esta regra entra em loop infinito no NfseClient.php.
        // Ela tenta inferir @param docblocks a partir de chamadas locais entre métodos,
        // mas os métodos interconectados (emitir→doEmitir→sendEvento→dispatchEvent)
        // causam re-análise cíclica infinita, travando o processo por >2 minutos.
        ClassMethodArrayDocblockParamFromLocalCallsRector::class,
        // Falso positivo: remove o primeiro argumento (concrete class) do bind(), quebrando o container.
        ContainerBindConcreteWithClosureOnlyRector::class,
    ]);
