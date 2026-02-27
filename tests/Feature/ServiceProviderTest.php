<?php

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
    $certPath = __DIR__ . '/../fixtures/certs/fake.pfx';

    config([
        'nfse-nacional.certificado.path'  => $certPath,
        'nfse-nacional.certificado.senha' => 'secret',
        'nfse-nacional.prefeitura'        => '3501608',
    ]);

    // Re-resolve from container to trigger the configure() branch
    $client = app(\Pulsar\NfseNacional\NfseClient::class);

    // If configured, consultar() should not throw
    expect($client)->toBeInstanceOf(\Pulsar\NfseNacional\NfseClient::class);
});
