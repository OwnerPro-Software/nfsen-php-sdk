<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;

covers(DanfseConfig::class);

it('constrói defaults a partir de array vazio', function () {
    $cfg = DanfseConfig::fromArray([]);

    expect($cfg)->toBeInstanceOf(DanfseConfig::class);
    expect($cfg->municipality)->toBeNull();
    // Default carrega logo padrão embutido do pacote; não-null esperado.
    expect($cfg->logoDataUri)->not->toBeNull();
});

it('logo_path false suprime o logo', function () {
    $cfg = DanfseConfig::fromArray(['logo_path' => false]);

    expect($cfg->logoDataUri)->toBeNull();
});

it('logo_data_uri precedência sobre logo_path', function () {
    $cfg = DanfseConfig::fromArray([
        'logo_path' => __DIR__.'/../../fixtures/danfse/tiny-logo.png',
        'logo_data_uri' => 'data:image/png;base64,OVERRIDE',
    ]);

    expect($cfg->logoDataUri)->toBe('data:image/png;base64,OVERRIDE');
});

it('carrega municipality quando name é string válida', function () {
    $cfg = DanfseConfig::fromArray([
        'municipality' => [
            'name' => 'Curitiba',
            'department' => 'PGM',
        ],
    ]);

    expect($cfg->municipality)->toBeInstanceOf(MunicipalityBranding::class);
    expect($cfg->municipality?->name)->toBe('Curitiba');
});

it('logo_path false tem precedência sobre logo_data_uri (suprime tudo)', function () {
    $cfg = DanfseConfig::fromArray([
        'logo_path' => false,
        'logo_data_uri' => 'data:image/png;base64,SHOULD_BE_DISCARDED',
    ]);

    expect($cfg->logoDataUri)->toBeNull();
});

it('chave enabled é ignorada (gate Laravel)', function () {
    $cfg = DanfseConfig::fromArray([
        'enabled' => true,
        'logo_path' => false,
    ]);

    expect($cfg->logoDataUri)->toBeNull();
});

it('enabled com valor não-bool também é ignorado (no-op)', function () {
    // Sanity: shape accept qualquer mixed em 'enabled', construtor apenas não reage.
    $cfg = DanfseConfig::fromArray([
        'enabled' => 'yes',
        'logo_path' => false,
    ]);

    expect($cfg)->toBeInstanceOf(DanfseConfig::class);
    expect($cfg->logoDataUri)->toBeNull();
});

it('municipality ausente → null', function () {
    $cfg = DanfseConfig::fromArray(['logo_path' => false]);
    expect($cfg->municipality)->toBeNull();
});

it('municipality: null explícito → null', function () {
    $cfg = DanfseConfig::fromArray(['logo_path' => false, 'municipality' => null]);
    expect($cfg->municipality)->toBeNull();
});

it('defesa em profundidade: municipality com name null → null', function () {
    $cfg = DanfseConfig::fromArray([
        'logo_path' => false,
        'municipality' => ['name' => null, 'department' => 'X'],
    ]);

    expect($cfg->municipality)->toBeNull();
});

it('defesa em profundidade: municipality com name string vazia → null', function () {
    $cfg = DanfseConfig::fromArray([
        'logo_path' => false,
        'municipality' => ['name' => '', 'department' => 'X'],
    ]);

    expect($cfg->municipality)->toBeNull();
});

it('lança em chave desconhecida no root', function () {
    DanfseConfig::fromArray(['logo_paht' => 'x']);
})->throws(InvalidArgumentException::class, 'danfse: chave(s) desconhecida(s): logo_paht');

it('lança quando logo_path não é string|false|null', function () {
    DanfseConfig::fromArray(['logo_path' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.logo_path: esperado string|false|null');

it('lança quando logo_data_uri não é string|null', function () {
    DanfseConfig::fromArray(['logo_data_uri' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.logo_data_uri: esperado string|null');

it('lança quando municipality não é array', function () {
    DanfseConfig::fromArray(['municipality' => 'string']);
})->throws(InvalidArgumentException::class, 'danfse.municipality: esperado array|null');
