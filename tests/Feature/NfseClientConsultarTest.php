<?php

use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\NfseClient;

it('consultar()->danfse returns DanfseResponse with url', function () {
    Http::fake(['*' => Http::response(['danfseUrl' => 'https://danfse.url/CHAVE123'], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->danfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->url)->toBe('https://danfse.url/CHAVE123');
});

it('consultar()->eventos returns EventosResponse', function () {
    Http::fake(['*' => Http::response(['eventos' => [['tipo' => '101101']]], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->eventos('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toHaveCount(1);
});

it('consultar()->eventos returns empty array when no events', function () {
    Http::fake(['*' => Http::response(['eventos' => []], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->eventos('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toBeEmpty();
});
