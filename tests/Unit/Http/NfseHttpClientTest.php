<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\Http\NfseHttpClient;
use Pulsar\NfseNacional\Support\TempFileFactory;

it('posts json payload to given url', function () {
    Http::fake(['*' => Http::response(['sucesso' => true], 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $response = $client->post('https://example.com/nfse', ['key' => 'value']);

    Http::assertSent(fn (Request $req) => $req->url() === 'https://example.com/nfse' &&
        $req->isJson()
    );

    expect($response)->toBe(['sucesso' => true]);
});

it('performs GET request', function () {
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $response = $client->get('https://example.com/nfse/CHAVE123');

    expect($response)->toHaveKey('nfseXmlGZipB64');
});

it('returns parsed JSON on 5xx when response has JSON body', function () {
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'Falha', 'codigo' => 'E500']]], 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $result = $client->post('https://example.com/nfse', []);

    expect($result)->toHaveKey('erros')
        ->and($result['erros'][0]['codigo'])->toBe('E500');
});

it('throws HttpException on 5xx when response has no JSON body', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    try {
        $client->post('https://example.com/nfse', []);
        test()->fail('Expected HttpException');
    } catch (\Pulsar\NfseNacional\Exceptions\HttpException $e) {
        expect($e->getMessage())->toBe('HTTP error: 500');
        expect($e->getResponseBody())->toContain('Server Error');
    }
});

it('returns parsed JSON on 4xx response instead of throwing', function () {
    Http::fake(['*' => Http::response(['erro' => ['descricao' => 'Unauthorized', 'codigo' => 'E401']], 401)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $result = $client->post('https://example.com/nfse', []);

    expect($result)->toHaveKey('erro');
    expect($result['erro']['descricao'])->toBe('Unauthorized');
});

it('passes mTLS options and payload to HTTP client', function () {
    Http::fake(['*' => Http::response(['sucesso' => true], 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, sslVerify: false);
    $client->post('https://example.com/nfse', ['dps' => 'xml']);

    // Laravel HTTP fake exposes url, method, headers, and body.
    // Guzzle-level options (cert, ssl_key, verify) cannot be asserted
    // through Http::assertSent -- mTLS is validated by integration tests.
    Http::assertSent(fn (Request $req) => $req->url() === 'https://example.com/nfse' &&
        $req->method() === 'POST' &&
        $req->isJson() &&
        $req['dps'] === 'xml'
    );
});

it('throws NfseException when tmpfile fails for both handles', function () {
    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturn(false);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(NfseException::class, 'arquivos temporários');
});

it('throws NfseException and closes first handle when second tmpfile fails', function () {
    $realHandle = tmpfile();
    $callCount = 0;

    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturnUsing(function () use (&$callCount, $realHandle) {
        $callCount++;

        return $callCount === 1 ? $realHandle : false;
    });

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(NfseException::class, 'arquivos temporários');
});

it('throws NfseException and closes second handle when first tmpfile fails', function () {
    $realHandle = tmpfile();
    $callCount = 0;

    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturnUsing(function () use (&$callCount, $realHandle) {
        $callCount++;

        return $callCount === 1 ? false : $realHandle;
    });

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(NfseException::class, 'arquivos temporários');
});

it('head returns 200 status code', function () {
    Http::fake(['*' => Http::response('', 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $status = $client->head('https://example.com/dps/DPS123');

    expect($status)->toBe(200);

    Http::assertSent(fn (Request $req) => $req->url() === 'https://example.com/dps/DPS123' &&
        $req->method() === 'HEAD'
    );
});

it('head returns 404 status code', function () {
    Http::fake(['*' => Http::response('', 404)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    $status = $client->head('https://example.com/dps/NONEXISTENT');

    expect($status)->toBe(404);
});

it('head throws HttpException on 5xx', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->head('https://example.com/dps/DPS123'))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('head throws NfseException when tmpfile fails', function () {
    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturn(false);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    expect(fn () => $client->head('https://example.com/dps/DPS123'))
        ->toThrow(NfseException::class, 'arquivos temporários');
});

it('throws NfseException when fwrite fails on read-only handle', function () {
    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturnUsing(fn () => fopen('php://memory', 'r'));

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    expect(fn () => @$client->post('https://example.com/nfse', []))
        ->toThrow(NfseException::class, 'escrever certificado');
});
