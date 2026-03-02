<?php

use Pulsar\NfseNacional\DTOs\EventosResponse;
use Pulsar\NfseNacional\DTOs\MensagemProcessamento;

it('success response carries xml and no erros', function () {
    $response = new EventosResponse(true, '<Evento/>');

    expect($response)
        ->sucesso->toBeTrue()
        ->xml->toBe('<Evento/>')
        ->erros->toBeEmpty();
});

it('success response with null xml', function () {
    $response = new EventosResponse(true);

    expect($response)
        ->sucesso->toBeTrue()
        ->xml->toBeNull()
        ->erros->toBeEmpty();
});

it('failure response carries erros and null xml', function () {
    $erros = [new MensagemProcessamento(descricao: 'NFSe não encontrada', codigo: 'E404')];

    $response = new EventosResponse(false, erros: $erros);

    expect($response)
        ->sucesso->toBeFalse()
        ->xml->toBeNull();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('NFSe não encontrada');
});

it('defaults all optional fields', function () {
    $response = new EventosResponse(true);

    expect($response)
        ->xml->toBeNull()
        ->erros->toBeEmpty()
        ->tipoAmbiente->toBeNull()
        ->versaoAplicativo->toBeNull()
        ->dataHoraProcessamento->toBeNull();
});

it('carries metadata fields', function () {
    $response = new EventosResponse(
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
