<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Http\NfseHttpClient;

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

it('certificate PEM output is valid for mTLS', function () {
    $cert = makeTestCertificate();

    $certPem = (string) $cert;
    expect($certPem)->toContain('-----BEGIN CERTIFICATE-----');
    $parsed = openssl_x509_parse($certPem);
    expect($parsed)->not->toBeFalse();

    $keyPem = (string) $cert->privateKey;
    expect($keyPem)->toContain('-----BEGIN');
    $key = openssl_pkey_get_private($keyPem);
    expect($key)->not->toBeFalse();
});
