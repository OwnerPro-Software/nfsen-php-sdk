<?php

use Pulsar\NfseNacional\DTOs\DanfseResponse;

it('stores a DANFSe success response', function () {
    $response = new DanfseResponse(true, 'https://danfse.url/CHAVE', null);

    expect($response->sucesso)->toBeTrue();
    expect($response->url)->toBe('https://danfse.url/CHAVE');
    expect($response->erro)->toBeNull();
});

it('stores a DANFSe failure response', function () {
    $response = new DanfseResponse(false, null, 'NFSe não encontrada');

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toBe('NFSe não encontrada');
});
