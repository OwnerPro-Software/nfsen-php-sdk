<?php

use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Facades\NfseNacional;
use OwnerPro\Nfsen\NfseClient;
use OwnerPro\Nfsen\NfseNacionalServiceProvider;

covers(NfseNacionalServiceProvider::class, NfseNacional::class);

it('resolves NfseClient from container', function () {
    config([
        'nfse-nacional.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfse-nacional.certificado.senha' => 'secret',
        'nfse-nacional.prefeitura' => '3501608',
    ]);

    $client = app(NfseClient::class);
    expect($client)->toBeInstanceOf(NfseClient::class);
});

it('NfseNacional facade resolves NfseClient', function () {
    config([
        'nfse-nacional.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfse-nacional.certificado.senha' => 'secret',
        'nfse-nacional.prefeitura' => '3501608',
    ]);

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
    $client = app(\OwnerPro\Nfsen\NfseClient::class);

    // If configured, consultar() returns a ConsultsNfse without throwing
    expect($client->consultar())->toBeInstanceOf(\OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse::class);
});

it('facade emitir works directly when config is set', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_FACADE'], 201)]);

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
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_FOR'], 200)]);

    $client = NfseNacional::for(makePfxContent(), 'secret', '9999999');

    expect($client)->toBeInstanceOf(NfseClient::class);
});

it('throws RuntimeException when cert file is empty', function () {
    $emptyFile = tempnam(sys_get_temp_dir(), 'nfse_test_');

    try {
        config([
            'nfse-nacional.certificado.path' => $emptyFile,
            'nfse-nacional.certificado.senha' => 'secret',
            'nfse-nacional.prefeitura' => '3501608',
        ]);

        expect(fn () => app(\OwnerPro\Nfsen\NfseClient::class))
            ->toThrow(\RuntimeException::class, 'Falha ao ler arquivo de certificado digital.');
    } finally {
        unlink($emptyFile);
    }
});

it('throws NfseException when cert config is incomplete', function () {
    expect(fn () => app(\OwnerPro\Nfsen\NfseClient::class))
        ->toThrow(NfseException::class, 'NfseClient não configurado');
});

it('throws NfseException when only certPath is missing', function () {
    config([
        'nfse-nacional.certificado.path' => null,
        'nfse-nacional.certificado.senha' => 'secret',
        'nfse-nacional.prefeitura' => '3501608',
    ]);

    expect(fn () => app(NfseClient::class))
        ->toThrow(NfseException::class, 'NfseClient não configurado');
});

it('throws NfseException when only senha is missing', function () {
    config([
        'nfse-nacional.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfse-nacional.certificado.senha' => '',
        'nfse-nacional.prefeitura' => '3501608',
    ]);

    expect(fn () => app(NfseClient::class))
        ->toThrow(NfseException::class, 'NfseClient não configurado');
});

it('throws NfseException when only prefeitura is missing', function () {
    config([
        'nfse-nacional.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfse-nacional.certificado.senha' => 'secret',
        'nfse-nacional.prefeitura' => '',
    ]);

    expect(fn () => app(NfseClient::class))
        ->toThrow(NfseException::class, 'NfseClient não configurado');
});

it('throws NfseException when certPath does not exist as file', function () {
    config([
        'nfse-nacional.certificado.path' => '/nonexistent/path/cert.pfx',
        'nfse-nacional.certificado.senha' => 'secret',
        'nfse-nacional.prefeitura' => '3501608',
    ]);

    expect(fn () => app(NfseClient::class))
        ->toThrow(NfseException::class, 'NfseClient não configurado');
});

it('casts integer prefeitura config to string', function () {
    config([
        'nfse-nacional.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfse-nacional.certificado.senha' => 'secret',
        'nfse-nacional.prefeitura' => 3501608,
    ]);

    $client = app(NfseClient::class);
    expect($client)->toBeInstanceOf(NfseClient::class);
});

it('publishes config file in console', function () {
    $paths = \Illuminate\Support\ServiceProvider::pathsToPublish(
        \OwnerPro\Nfsen\NfseNacionalServiceProvider::class,
        'nfse-nacional-config',
    );

    expect($paths)->toBeArray()->not->toBeEmpty();

    $sourcePath = array_key_first($paths);
    expect($sourcePath)->toEndWith('config/nfse-nacional.php');
    expect(file_exists($sourcePath))->toBeTrue();
    expect($paths[$sourcePath])->toContain('nfse-nacional.php');
});
