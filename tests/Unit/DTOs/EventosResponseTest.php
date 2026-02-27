<?php

use Pulsar\NfseNacional\DTOs\EventosResponse;

it('stores eventos success response', function () {
    $eventos = [['tipo' => '101101', 'desc' => 'Cancelamento']];
    $response = new EventosResponse(true, $eventos, null);

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toHaveCount(1);
    expect($response->erro)->toBeNull();
});

it('stores eventos empty response', function () {
    $response = new EventosResponse(true, [], null);

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toBeEmpty();
});
