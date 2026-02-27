<?php

use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\NfseClient;

// makePfxContent() definida em tests/helpers.php (criado na Task 8)

it('emitir returns success NfseResponse', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/emitir_sucesso.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->not->toBeNull();
})->with('dpsData');

it('emitir returns rejection NfseResponse on erro field', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/emitir_rejeicao.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->not->toBeNull();
})->with('dpsData');

it('consultar()->nfse returns success NfseResponse', function () {
    $xmlB64 = base64_encode(gzencode('<NFSe/>'));
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => $xmlB64], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->nfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toContain('<NFSe');
});

it('consultar()->dps returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(['dps' => 'dados'], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->dps('CHAVE123');

    expect($response->sucesso)->toBeTrue();
});

it('throws InvalidArgumentException for invalid IBGE code', function () {
    expect(fn () => NfseClient::for(makePfxContent(), 'secret', '123'))
        ->toThrow(\InvalidArgumentException::class, 'IBGE');
});

it('forStandalone creates client without Laravel container', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_STANDALONE'], 200)]);

    $client = NfseClient::forStandalone(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_STANDALONE');
})->with('dpsData');

it('emitir returns rejection with erros array', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/emitir_rejeicao.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toContain('CNPJ');
})->with('dpsData');
