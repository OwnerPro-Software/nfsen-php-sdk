<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\NfseClient;

it('consultar()->danfse returns DanfseResponse with url', function () {
    Http::fake(['*' => Http::response(['danfseUrl' => 'https://danfse.url/CHAVE123'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->danfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->url)->toBe('https://danfse.url/CHAVE123');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://adn.producaorestrita.nfse.gov.br/danfse/CHAVE123' &&
        $req->method() === 'GET'
    );
});

it('consultar()->eventos returns EventosResponse', function () {
    Http::fake(['*' => Http::response(['eventos' => [['tipo' => '101101']]], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->eventos('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toHaveCount(1);

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/CHAVE123/eventos/101101/1' &&
        $req->method() === 'GET'
    );
});

it('consultar()->eventos returns empty array when no events', function () {
    Http::fake(['*' => Http::response(['eventos' => []], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->eventos('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->eventos)->toBeEmpty();
});

it('consultar()->nfse returns rejection on erros response', function () {
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'NFSe não encontrada', 'codigo' => '404']]], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->nfse('CHAVE_INVALIDA');

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toBe('NFSe não encontrada');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/CHAVE_INVALIDA' &&
        $req->method() === 'GET'
    );
});

it('consultar()->nfse throws HttpException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->nfse('CHAVE123'))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('consultar()->danfse returns failure on erros response', function () {
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'DANFSe não encontrada', 'codigo' => '404']]], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->danfse('CHAVE_INVALIDA');

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toBe('DANFSe não encontrada');
});

it('executeGetRaw returns raw array including error keys', function () {
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'Erro na consulta', 'codigo' => '500']]], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $result = $client->executeGetRaw('https://fake.url/test');

    expect($result)->toHaveKey('erros');
    expect($result['erros'][0]['descricao'])->toBe('Erro na consulta');
});

it('executeGetRaw throws HttpException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->executeGetRaw('https://fake.url/test'))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('consultar()->eventos throws HttpException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->eventos('CHAVE123'))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('consultar throws NfseException when client is not configured', function () {
    $resolver = new \Pulsar\NfseNacional\Services\PrefeituraResolver(__DIR__.'/../../storage/prefeituras.json');
    $dpsBuilder = new \Pulsar\NfseNacional\Xml\DpsBuilder(__DIR__.'/../../storage/schemes');

    $client = new NfseClient(
        ambiente: \Pulsar\NfseNacional\Enums\NfseAmbiente::HOMOLOGACAO,
        timeout: 30,
        signingAlgorithm: 'sha1',
        sslVerify: true,
        prefeituraResolver: $resolver,
        dpsBuilder: $dpsBuilder,
    );

    expect(fn () => $client->consultar())
        ->toThrow(\Pulsar\NfseNacional\Exceptions\NfseException::class, 'não configurado');
});

it('consultar()->nfse throws NfseException on invalid base64 response', function () {
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => '!!!invalid-base64!!!'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->nfse('CHAVE123'))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\NfseException::class, 'base64');
});

it('consultar()->nfse throws NfseException on invalid gzip response', function () {
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => base64_encode('not-gzip-data')], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->nfse('CHAVE123'))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\NfseException::class, 'descomprimir');
});

it('consultar()->danfse uses Santa Ana de Parnaiba custom operation path', function () {
    Http::fake(['*' => Http::response(['danfseUrl' => 'https://danfse.url/PDF'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3547304');
    $response = $client->consultar()->danfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();

    Http::assertSent(fn (Request $req) => $req->url() === 'https://producaorestrita.simplissweb.com.br/nfse/CHAVE123' &&
        $req->method() === 'GET'
    );
});
