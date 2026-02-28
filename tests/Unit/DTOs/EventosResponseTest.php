<?php

use Pulsar\NfseNacional\DTOs\EventosResponse;

it('success response carries eventos array and no erro', function () {
    $eventos = [
        ['tpEvento' => '101101', 'descEvento' => 'Cancelamento por Erro', 'dhEvento' => '2026-02-27T10:00:00-03:00'],
        ['tpEvento' => '105102', 'descEvento' => 'Cancelamento por Substituição', 'dhEvento' => '2026-02-27T11:00:00-03:00'],
    ];

    $response = new EventosResponse(true, $eventos, null);

    expect($response)
        ->sucesso->toBeTrue()
        ->eventos->toHaveCount(2)
        ->erro->toBeNull();
    expect($response->eventos[0]['tpEvento'])->toBe('101101');
    expect($response->eventos[1]['tpEvento'])->toBe('105102');
});

it('success response with empty eventos', function () {
    $response = new EventosResponse(true, [], null);

    expect($response)
        ->sucesso->toBeTrue()
        ->eventos->toBeEmpty()
        ->erro->toBeNull();
});

it('failure response carries erro and empty eventos', function () {
    $response = new EventosResponse(false, [], 'NFSe não encontrada');

    expect($response)
        ->sucesso->toBeFalse()
        ->eventos->toBeEmpty()
        ->erro->toBe('NFSe não encontrada');
});
