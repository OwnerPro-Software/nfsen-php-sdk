<?php

covers(\Pulsar\NfseNacional\Responses\EventsResponse::class);

use Pulsar\NfseNacional\Responses\EventsResponse;
use Pulsar\NfseNacional\Responses\ProcessingMessage;

it('success response carries xml and no erros', function () {
    $response = new EventsResponse(true, '<Evento/>');

    expect($response)
        ->sucesso->toBeTrue()
        ->xml->toBe('<Evento/>')
        ->erros->toBeEmpty();
});

it('success response with null xml', function () {
    $response = new EventsResponse(true);

    expect($response)
        ->sucesso->toBeTrue()
        ->xml->toBeNull()
        ->erros->toBeEmpty();
});

it('failure response carries erros and null xml', function () {
    $erros = [new ProcessingMessage(descricao: 'NFSe não encontrada', codigo: 'E404')];

    $response = new EventsResponse(false, erros: $erros);

    expect($response)
        ->sucesso->toBeFalse()
        ->xml->toBeNull();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('NFSe não encontrada');
});

it('defaults all optional fields', function () {
    $response = new EventsResponse(true);

    expect($response)
        ->xml->toBeNull()
        ->erros->toBeEmpty()
        ->tipoAmbiente->toBeNull()
        ->versaoAplicativo->toBeNull()
        ->dataHoraProcessamento->toBeNull();
});

it('carries metadata fields', function () {
    $response = new EventsResponse(
        sucesso: true,
        xml: '<Evento/>',
        tipoAmbiente: 2,
        versaoAplicativo: '1.0.0',
        dataHoraProcessamento: '2026-03-02T12:00:00-03:00',
    );

    expect($response)
        ->tipoAmbiente->toBe(2)
        ->versaoAplicativo->toBe('1.0.0')
        ->dataHoraProcessamento->toBe('2026-03-02T12:00:00-03:00');
});
