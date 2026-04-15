<?php

use OwnerPro\Nfsen\Danfse\LogoLoader;
use OwnerPro\Nfsen\Danfse\MunicipalityBranding;

covers(MunicipalityBranding::class, LogoLoader::class);

it('accepts only name', function () {
    $b = new MunicipalityBranding(name: 'Prefeitura X');
    expect($b->name)->toBe('Prefeitura X');
    expect($b->department)->toBe('');
    expect($b->email)->toBe('');
    expect($b->logoDataUri)->toBeNull();
});

it('reads logo from path', function () {
    $path = __DIR__.'/../../fixtures/danfse/tiny-logo.png';

    $b = new MunicipalityBranding(name: 'X', logoPath: $path);

    // Deve começar com 'data:', conter o mime resolvido, ';base64,' e o conteúdo encoded.
    expect($b->logoDataUri)->toStartWith('data:image/png;base64,');
    $prefix = 'data:image/png;base64,';
    /** @var string $dataUri */
    $dataUri = $b->logoDataUri;
    $encoded = substr($dataUri, strlen($prefix));
    expect(base64_decode($encoded, true))->toBe(file_get_contents($path));
});

it('prefers logoDataUri over logoPath', function () {
    $path = __DIR__.'/../../fixtures/danfse/tiny-logo.png';

    $b = new MunicipalityBranding(
        name: 'X',
        logoDataUri: 'data:image/png;base64,DIRECT',
        logoPath: $path,
    );

    expect($b->logoDataUri)->toBe('data:image/png;base64,DIRECT');
});

it('throws for missing logo file with path in message', function () {
    expect(fn () => new MunicipalityBranding(name: 'X', logoPath: '/nope/missing.png'))
        ->toThrow(InvalidArgumentException::class, 'Arquivo de logo não encontrado ou ilegível: /nope/missing.png');
});

it('accepts null logoPath leaving logoDataUri null', function () {
    $b = new MunicipalityBranding(name: 'X', logoPath: null);

    expect($b->logoDataUri)->toBeNull();
});
