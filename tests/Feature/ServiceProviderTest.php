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
