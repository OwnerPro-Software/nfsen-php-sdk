<?php

use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\NfseClient;

it('cancelar returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_sucesso.json'), true),
        200
    )]);

    $pfx      = file_get_contents(__DIR__ . '/../fixtures/certs/fake-icpbr.pfx');
    $client   = NfseClient::for($pfx, 'secret', '3501608');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeTrue();
});

it('cancelar returns rejection NfseResponse on erro field', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_rejeicao.json'), true),
        200
    )]);

    $pfx      = file_get_contents(__DIR__ . '/../fixtures/certs/fake-icpbr.pfx');
    $client   = NfseClient::for($pfx, 'secret', '3501608');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toContain('não encontrada');
});

it('cancelar works with cert without ICP-Brasil OID', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/cancelar_sucesso.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->cancelar(
        'CHAVE50CARACTERES1234567890123456789012345678901',
        MotivoCancelamento::ErroEmissao,
        'Erro ao emitir'
    );

    expect($response->sucesso)->toBeTrue();
});
