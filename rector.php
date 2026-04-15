<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\TypeDeclarationDocblocks\Rector\Class_\ClassMethodArrayDocblockParamFromLocalCallsRector;
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
    ->withPhpSets(php83: true)
    ->withImportNames(removeUnusedImports: true)
    ->withSkip([
        // fromArray() methods use @phpstan-param with typed array shapes — this rule would add redundant @param array<string, mixed>
        AddParamArrayDocblockFromDimFetchAccessRector::class,
        // Bug no Rector 2.3.8: esta regra entra em loop infinito no NfsenClient.php.
        // Ela tenta inferir @param docblocks a partir de chamadas locais entre métodos,
        // mas os métodos interconectados (emitir→doEmitir→sendEvento→dispatchEvent)
        // causam re-análise cíclica infinita, travando o processo por >2 minutos.
        ClassMethodArrayDocblockParamFromLocalCallsRector::class,
        // DanfseHtmlRenderer expõe variáveis (`$data`, `$qrCode`, `$logo`, `$municipality`, `$h`)
        // ao template incluído via `include`. Rector não rastreia esse uso e tenta remover as
        // variáveis/parâmetros como dead code, quebrando a renderização.
        RemoveUnusedVariableAssignRector::class => [
            __DIR__.'/src/Adapters/DanfseHtmlRenderer.php',
        ],
        RemoveUnusedPrivateMethodParameterRector::class => [
            __DIR__.'/src/Adapters/DanfseHtmlRenderer.php',
        ],
        // SimpleXMLElement: `isset($node->child)` é a forma idiomática de verificar
        // presença de filhos no XML. `property_exists` retorna false para filhos
        // dinâmicos populados via namespace/children, levando a falsos negativos.
        IssetOnPropertyObjectToPropertyExistsRector::class => [
            __DIR__.'/src/Adapters/DanfseDataBuilder.php',
        ],
    ]);
