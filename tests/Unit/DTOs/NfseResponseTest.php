<?php

use Pulsar\NfseNacional\DTOs\NfseResponse;

it('success response carries chave and no erro', function () {
    $response = new NfseResponse(true, 'chave123', '<NFSe/>', null);

    expect($response)
        ->sucesso->toBeTrue()
        ->chave->toBe('chave123')
        ->xml->toBe('<NFSe/>')
        ->erro->toBeNull();
});

it('failure response carries erro and no chave', function () {
    $response = new NfseResponse(false, null, null, 'E001 - Erro');

    expect($response)
        ->sucesso->toBeFalse()
        ->chave->toBeNull()
        ->xml->toBeNull()
        ->erro->toBe('E001 - Erro');
});

it('success response can carry xml with decoded gzip content', function () {
    $originalXml = '<NFSe xmlns="http://www.sped.fazenda.gov.br/nfse"><infNFSe/></NFSe>';
    $decoded = gzdecode(base64_decode(base64_encode((string) gzencode($originalXml)))) ?: null;

    $response = new NfseResponse(true, null, $decoded, null);

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toBe($originalXml);
});

it('success response without xml has null xml', function () {
    $response = new NfseResponse(true, 'CHAVE50', null, null);

    expect($response)
        ->sucesso->toBeTrue()
        ->chave->toBe('CHAVE50')
        ->xml->toBeNull();
});

it('success response without chave has null chave', function () {
    $response = new NfseResponse(true, null, null, null);

    expect($response)
        ->sucesso->toBeTrue()
        ->chave->toBeNull();
});
