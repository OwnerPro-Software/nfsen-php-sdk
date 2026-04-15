<?php

declare(strict_types=1);

use OwnerPro\Nfsen\Danfse\MunicipalityBranding;

covers(MunicipalityBranding::class);

it('constrói a partir de array completo', function () {
    $mun = MunicipalityBranding::fromArray([
        'name' => 'São Paulo',
        'department' => 'SF/SUBTES',
        'email' => 'nfse@sp.gov.br',
        'logo_path' => null,
        'logo_data_uri' => 'data:image/png;base64,AAA',
    ]);

    expect($mun->name)->toBe('São Paulo');
    expect($mun->department)->toBe('SF/SUBTES');
    expect($mun->email)->toBe('nfse@sp.gov.br');
    expect($mun->logoDataUri)->toBe('data:image/png;base64,AAA');
});

it('defaults department e email para string vazia quando omitidos', function () {
    $mun = MunicipalityBranding::fromArray(['name' => 'Rio de Janeiro']);

    expect($mun->department)->toBe('');
    expect($mun->email)->toBe('');
    expect($mun->logoDataUri)->toBeNull();
});

it('resolve logo_path para data URI', function () {
    $mun = MunicipalityBranding::fromArray([
        'name' => 'Porto Alegre',
        'logo_path' => __DIR__.'/../../fixtures/danfse/tiny-logo.png',
    ]);

    expect($mun->logoDataUri)->toStartWith('data:image/png;base64,');
});

it('logo_data_uri precedência sobre logo_path', function () {
    $mun = MunicipalityBranding::fromArray([
        'name' => 'Curitiba',
        'logo_path' => __DIR__.'/../../fixtures/danfse/tiny-logo.png',
        'logo_data_uri' => 'data:image/png;base64,OVERRIDE',
    ]);

    expect($mun->logoDataUri)->toBe('data:image/png;base64,OVERRIDE');
});

it('lança em chave desconhecida', function () {
    MunicipalityBranding::fromArray(['name' => 'X', 'foo' => 1]);
})->throws(InvalidArgumentException::class, 'danfse.municipality: chave(s) desconhecida(s): foo');

it('lança quando name está ausente', function () {
    MunicipalityBranding::fromArray(['department' => 'X']);
})->throws(InvalidArgumentException::class, 'danfse.municipality.name: obrigatório');

it('lança quando name é string vazia', function () {
    MunicipalityBranding::fromArray(['name' => '']);
})->throws(InvalidArgumentException::class, 'danfse.municipality.name: não pode ser vazio');

it('lança quando name não é string', function () {
    MunicipalityBranding::fromArray(['name' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.municipality.name: esperado string');

it('lança quando department não é string', function () {
    MunicipalityBranding::fromArray(['name' => 'X', 'department' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.municipality.department: esperado string');

it('lança quando email não é string', function () {
    MunicipalityBranding::fromArray(['name' => 'X', 'email' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.municipality.email: esperado string');

it('lança quando logo_path não é string|null', function () {
    MunicipalityBranding::fromArray(['name' => 'X', 'logo_path' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.municipality.logo_path: esperado string|null');

it('lança quando logo_data_uri não é string|null', function () {
    MunicipalityBranding::fromArray(['name' => 'X', 'logo_data_uri' => 123]);
})->throws(InvalidArgumentException::class, 'danfse.municipality.logo_data_uri: esperado string|null');
