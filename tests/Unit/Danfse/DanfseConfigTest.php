<?php

use OwnerPro\Nfsen\Danfse\DanfseConfig;
use OwnerPro\Nfsen\Danfse\LogoLoader;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;

covers(DanfseConfig::class, LogoLoader::class);

it('uses package default logo when no args', function () {
    $c = new DanfseConfig;
    expect($c->logoDataUri)->toStartWith('data:image/');
});

it('reads custom logo from path', function () {
    $path = __DIR__.'/../../fixtures/danfse/tiny-logo.png';
    $c = new DanfseConfig(logoPath: $path);

    expect($c->logoDataUri)->toStartWith('data:image/png;base64,');
    $prefix = 'data:image/png;base64,';
    /** @var string $dataUri */
    $dataUri = $c->logoDataUri;
    $encoded = substr($dataUri, strlen($prefix));
    expect(base64_decode($encoded, true))->toBe(file_get_contents($path));
});

it('suppresses logo when logoPath is false', function () {
    $c = new DanfseConfig(logoPath: false);
    expect($c->logoDataUri)->toBeNull();
});

it('false logoPath overrides logoDataUri', function () {
    $c = new DanfseConfig(logoDataUri: 'data:image/png;base64,X', logoPath: false);
    expect($c->logoDataUri)->toBeNull();
});

it('prefers logoDataUri over logoPath', function () {
    $path = __DIR__.'/../../fixtures/danfse/tiny-logo.png';
    $c = new DanfseConfig(logoDataUri: 'data:image/png;base64,DIRECT', logoPath: $path);
    expect($c->logoDataUri)->toBe('data:image/png;base64,DIRECT');
});

it('carries municipality branding', function () {
    $branding = new MunicipalityBranding(name: 'X');
    $c = new DanfseConfig(municipality: $branding);
    expect($c->municipality)->toBe($branding);
});

it('throws for missing logo path with path in message', function () {
    expect(fn () => new DanfseConfig(logoPath: '/nope/missing.png'))
        ->toThrow(InvalidArgumentException::class, 'Arquivo de logo não encontrado ou ilegível: /nope/missing.png');
});
