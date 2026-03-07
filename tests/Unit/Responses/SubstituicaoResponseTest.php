<?php

covers(\Pulsar\NfseNacional\Responses\SubstituicaoResponse::class);

use Pulsar\NfseNacional\Responses\NfseResponse;
use Pulsar\NfseNacional\Responses\SubstituicaoResponse;

it('creates successful response when both emissao and evento succeed', function () {
    $emissao = new NfseResponse(sucesso: true, chave: 'CHAVE_SUB');
    $evento = new NfseResponse(sucesso: true, chave: 'CHAVE_ORIG');

    $response = new SubstituicaoResponse(sucesso: true, emissao: $emissao, evento: $evento);

    expect($response->sucesso)->toBeTrue();
    expect($response->emissao)->toBe($emissao);
    expect($response->evento)->toBe($evento);
});

it('creates failed response when emissao fails', function () {
    $emissao = new NfseResponse(sucesso: false);

    $response = new SubstituicaoResponse(sucesso: false, emissao: $emissao, evento: null);

    expect($response->sucesso)->toBeFalse();
    expect($response->emissao)->toBe($emissao);
    expect($response->evento)->toBeNull();
});

it('creates failed response when emissao succeeds but evento fails', function () {
    $emissao = new NfseResponse(sucesso: true, chave: 'CHAVE_SUB');
    $evento = new NfseResponse(sucesso: false);

    $response = new SubstituicaoResponse(sucesso: false, emissao: $emissao, evento: $evento);

    expect($response->sucesso)->toBeFalse();
    expect($response->emissao->sucesso)->toBeTrue();
    expect($response->evento->sucesso)->toBeFalse();
});
