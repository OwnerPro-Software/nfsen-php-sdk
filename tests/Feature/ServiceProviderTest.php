<?php

use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\DTOs\Dps\DpsData;
use Pulsar\NfseNacional\Facades\NfseNacional;
use Pulsar\NfseNacional\NfseClient;

it('resolves NfseClient from container', function () {
    $client = app(NfseClient::class);
    expect($client)->toBeInstanceOf(NfseClient::class);
});

it('NfseNacional facade resolves NfseClient', function () {
    expect(NfseNacional::getFacadeRoot())->toBeInstanceOf(NfseClient::class);
});

it('config nfse-nacional is published', function () {
    expect(config('nfse-nacional.ambiente'))->not->toBeNull();
});

it('configures client when cert path, senha and prefeitura are set', function () {
    $certPath = __DIR__.'/../fixtures/certs/fake.pfx';

    config([
        'nfse-nacional.certificado.path' => $certPath,
        'nfse-nacional.certificado.senha' => 'secret',
        'nfse-nacional.prefeitura' => '3501608',
    ]);

    // Re-resolve from container to trigger the configure() branch
    $client = app(\Pulsar\NfseNacional\NfseClient::class);

    // If configured, consultar() returns a ConsultaBuilder without throwing
    expect($client->consultar())->toBeInstanceOf(\Pulsar\NfseNacional\Consulta\ConsultaBuilder::class);
});

it('facade emitir works directly when config is set', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_FACADE'], 200)]);

    config([
        'nfse-nacional.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfse-nacional.certificado.senha' => 'secret',
        'nfse-nacional.prefeitura' => '3501608',
    ]);

    $response = NfseNacional::emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_FACADE');
})->with('dpsData');

it('facade for() returns configured NfseClient without double resolution', function () {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_FOR'], 200)]);

    $client = NfseNacional::for(makePfxContent(), 'secret', '9999999');

    expect($client)->toBeInstanceOf(NfseClient::class);
});

it('throws RuntimeException when cert file exists but cannot be read', function () {
    config([
        'nfse-nacional.certificado.path' => __DIR__.'/../fixtures/certs',
        'nfse-nacional.certificado.senha' => 'secret',
        'nfse-nacional.prefeitura' => '3501608',
    ]);

    expect(fn () => app(\Pulsar\NfseNacional\NfseClient::class))
        ->toThrow(\RuntimeException::class, 'Falha ao ler certificado');
});
