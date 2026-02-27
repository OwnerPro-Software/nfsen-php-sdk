<?php

use Pulsar\NfseNacional\DTOs\NfseResponse;

it('stores a success response', function () {
    $response = new NfseResponse(true, 'chave123', '<xml/>', null);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('chave123');
    expect($response->xml)->toBe('<xml/>');
    expect($response->erro)->toBeNull();
});

it('stores a failure response', function () {
    $response = new NfseResponse(false, null, null, 'E001 - Erro');

    expect($response->sucesso)->toBeFalse();
    expect($response->chave)->toBeNull();
    expect($response->xml)->toBeNull();
    expect($response->erro)->toBe('E001 - Erro');
});
