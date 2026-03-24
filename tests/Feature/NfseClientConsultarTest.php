<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Exceptions\HttpException;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\NfseClient;

covers(NfseClient::class);

it('consultar()->danfse returns DanfseResponse with pdf', function () {
    $chave = makeChaveAcesso();
    Http::fake(['*' => Http::response('PDF-BINARY-CONTENT', 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->danfse($chave);

    expect($response->sucesso)->toBeTrue();
    expect($response->pdf)->toBe('PDF-BINARY-CONTENT');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://adn.producaorestrita.nfse.gov.br/danfse/'.$chave &&
        $req->method() === 'GET'
    );
});

it('consultar()->eventos returns EventsResponse', function () {
    $chave = makeChaveAcesso();
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->eventos($chave);

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toContain('<Evento');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/'.$chave.'/eventos/101101/1' &&
        $req->method() === 'GET'
    );
});

it('consultar()->eventos returns null xml when no eventoXmlGZipB64', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->eventos(makeChaveAcesso());

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toBeNull();
});

it('consultar()->nfse returns rejection on erros response', function () {
    $chave = makeChaveAcesso();
    Http::fake(['*' => Http::response(['erro' => ['descricao' => 'NFSe não encontrada', 'codigo' => '404']], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->nfse($chave);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toBe('NFSe não encontrada');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/'.$chave &&
        $req->method() === 'GET'
    );
});

it('consultar()->nfse throws HttpException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->nfse(makeChaveAcesso()))
        ->toThrow(HttpException::class);
});

it('consultar()->danfse returns failure on HTTP error', function () {
    Http::fake(['*' => Http::response('Not Found', 404)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->danfse(makeChaveAcesso());

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toHaveCount(1);
    expect($response->erros[0]->codigo)->toBe('404');
});

it('consultar()->eventos throws HttpException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->eventos(makeChaveAcesso()))
        ->toThrow(HttpException::class);
});

it('consultar()->nfse throws NfseException on invalid base64 response', function () {
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => '!!!invalid-base64!!!'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->nfse(makeChaveAcesso()))
        ->toThrow(NfseException::class, 'base64');
});

it('consultar()->nfse throws NfseException on invalid gzip response', function () {
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => base64_encode('not-gzip-data')], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->nfse(makeChaveAcesso()))
        ->toThrow(NfseException::class, 'descomprimir');
});

it('consultar()->verificarDps returns true on 200', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $result = $client->consultar()->verificarDps('DPS123');

    expect($result)->toBeTrue();

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/dps/DPS123' &&
        $req->method() === 'HEAD'
    );
});

it('consultar()->verificarDps returns false on 404', function () {
    Http::fake(['*' => Http::response('', 404)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $result = $client->consultar()->verificarDps('DPS123');

    expect($result)->toBeFalse();
});

it('consultar()->verificarDps throws HttpException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->verificarDps('DPS123'))
        ->toThrow(HttpException::class);
});

it('consultar()->verificarDps throws on non-HTTP exception', function () {
    Http::fake(['*' => function (): never {
        throw new RuntimeException('Connection reset');
    }]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->consultar()->verificarDps('DPS123'))
        ->toThrow(RuntimeException::class, 'Connection reset');
});

it('consultar()->danfse uses Santa Ana de Parnaiba custom operation path', function () {
    $chave = makeChaveAcesso();
    Http::fake(['*' => Http::response('PDF-CONTENT', 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3547304');
    $response = $client->consultar()->danfse($chave);

    expect($response->sucesso)->toBeTrue();

    Http::assertSent(fn (Request $req) => $req->url() === 'https://producaorestrita.simplissweb.com.br/nfse/'.$chave &&
        $req->method() === 'GET'
    );
});
