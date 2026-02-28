<?php

use Pulsar\NfseNacional\DTOs\DanfseResponse;

it('success response carries danfse url and no erro', function () {
    $response = new DanfseResponse(true, 'https://danfse.exemplo.com/CHAVE123', null);

    expect($response)
        ->sucesso->toBeTrue()
        ->url->toBe('https://danfse.exemplo.com/CHAVE123')
        ->erro->toBeNull();
});

it('failure response carries erro and no url', function () {
    $response = new DanfseResponse(false, null, 'NFSe não encontrada');

    expect($response)
        ->sucesso->toBeFalse()
        ->url->toBeNull()
        ->erro->toBe('NFSe não encontrada');
});

it('maps from real fixture response shape', function () {
    /** @var array{danfseUrl?: string, erros?: list<array{descricao?: string}>} $fixture */
    $fixture = json_decode(
        file_get_contents(__DIR__ . '/../../fixtures/responses/consultar_danfse.json'),
        true,
    );

    $response = new DanfseResponse(
        sucesso: isset($fixture['danfseUrl']),
        url:     $fixture['danfseUrl'] ?? null,
        erro:    null,
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->url)->toBe('https://danfse.exemplo.com/CHAVE123');
});
