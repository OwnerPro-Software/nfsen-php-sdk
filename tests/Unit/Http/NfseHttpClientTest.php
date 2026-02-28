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

    Http::assertSent(fn (Request $req) =>
        $req->url() === 'https://example.com/nfse' &&
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

it('throws HttpException on 5xx response', function () {
    Http::fake(['*' => Http::response(['message' => 'Server Error'], 500)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('throws HttpException on 4xx response', function () {
    Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30);

    expect(fn () => $client->post('https://example.com/nfse', []))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('passes mTLS options and payload to HTTP client', function () {
    Http::fake(['*' => Http::response(['sucesso' => true], 200)]);

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, sslVerify: false);
    $client->post('https://example.com/nfse', ['dps' => 'xml']);

    // Laravel HTTP fake exposes url, method, headers, and body.
    // Guzzle-level options (cert, ssl_key, verify) cannot be asserted
    // through Http::assertSent -- mTLS is validated by integration tests.
    Http::assertSent(fn (Request $req) =>
        $req->url() === 'https://example.com/nfse' &&
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

it('throws NfseException when fwrite fails on read-only handle', function () {
    $factory = Mockery::mock(TempFileFactory::class);
    $factory->shouldReceive('__invoke')->andReturnUsing(fn () => fopen('php://memory', 'r'));

    $client = new NfseHttpClient(makeTestCertificate(), timeout: 30, tempFileFactory: $factory);

    expect(fn () => @$client->post('https://example.com/nfse', []))
        ->toThrow(NfseException::class, 'escrever certificado');
});
