<?php

covers(\OwnerPro\Nfsen\Responses\DanfseResponse::class);

use OwnerPro\Nfsen\Responses\DanfseResponse;
use OwnerPro\Nfsen\Responses\ProcessingMessage;

it('success response carries pdf bytes and no erros', function () {
    $response = new DanfseResponse(true, 'PDF-BINARY-CONTENT');

    expect($response)
        ->sucesso->toBeTrue()
        ->pdf->toBe('PDF-BINARY-CONTENT')
        ->erros->toBeEmpty();
});

it('failure response carries erros and no pdf', function () {
    $erros = [new ProcessingMessage(descricao: 'NFSe não encontrada', codigo: 'E404')];

    $response = new DanfseResponse(false, erros: $erros);

    expect($response)
        ->sucesso->toBeFalse()
        ->pdf->toBeNull();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->descricao)->toBe('NFSe não encontrada');
});

it('defaults all optional fields', function () {
    $response = new DanfseResponse(true);

    expect($response)
        ->pdf->toBeNull()
        ->erros->toBeEmpty();
});
