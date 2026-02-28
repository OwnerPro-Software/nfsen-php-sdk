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

it('consultar()->nfse returns rejection on erros response', function () {
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'NFSe não encontrada', 'codigo' => '404']]], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->nfse('CHAVE_INVALIDA');

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toBe('NFSe não encontrada');
});

it('consultar()->nfse throws HttpException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');

    expect(fn () => $client->consultar()->nfse('CHAVE123'))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('consultar()->danfse returns failure on erros response', function () {
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'DANFSe não encontrada', 'codigo' => '404']]], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->danfse('CHAVE_INVALIDA');

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toBe('DANFSe não encontrada');
});

it('executeGetRaw returns raw array including error keys', function () {
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'Erro na consulta', 'codigo' => '500']]], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $result = $client->executeGetRaw('https://fake.url/test');

    expect($result)->toHaveKey('erros');
    expect($result['erros'][0]['descricao'])->toBe('Erro na consulta');
});

it('executeGetRaw throws HttpException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');

    expect(fn () => $client->executeGetRaw('https://fake.url/test'))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});
