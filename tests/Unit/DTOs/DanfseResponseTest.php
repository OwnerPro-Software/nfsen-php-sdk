<?php

use Pulsar\NfseNacional\Responses\DanfseResponse;
use Pulsar\NfseNacional\Responses\ProcessingMessage;

it('success response carries danfse url and no erros', function () {
    $response = new DanfseResponse(true, 'https://danfse.exemplo.com/CHAVE123');

    expect($response)
        ->sucesso->toBeTrue()
        ->url->toBe('https://danfse.exemplo.com/CHAVE123')
        ->erros->toBeEmpty();
});

it('failure response carries erros and no url', function () {
    $erros = [new ProcessingMessage(descricao: 'NFSe não encontrada', codigo: 'E404')];

    $response = new DanfseResponse(false, erros: $erros);

    expect($response)
        ->sucesso->toBeFalse()
        ->url->toBeNull();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('NFSe não encontrada');
});

it('maps from real fixture response shape', function () {
    /** @var array{danfseUrl?: string} $fixture */
    $fixture = json_decode(
        file_get_contents(__DIR__.'/../../fixtures/responses/consultar_danfse.json'),
        true,
    );

    $response = new DanfseResponse(
        sucesso: isset($fixture['danfseUrl']),
        url: $fixture['danfseUrl'] ?? null,
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->url)->toBe('https://danfse.exemplo.com/CHAVE123');
    expect($response->erros)->toBeEmpty();
});

it('defaults all optional fields', function () {
    $response = new DanfseResponse(true);

    expect($response)
        ->url->toBeNull()
        ->erros->toBeEmpty()
        ->tipoAmbiente->toBeNull()
        ->versaoAplicativo->toBeNull()
        ->dataHoraProcessamento->toBeNull();
});

it('carries metadata fields', function () {
    $response = new DanfseResponse(
        sucesso: true,
        url: 'https://danfse.url/PDF',
        tipoAmbiente: 2,
        versaoAplicativo: '1.0.0',
        dataHoraProcessamento: '2026-03-02T12:00:00-03:00',
    );

    expect($response)
        ->tipoAmbiente->toBe(2)
        ->versaoAplicativo->toBe('1.0.0')
        ->dataHoraProcessamento->toBe('2026-03-02T12:00:00-03:00');
});
