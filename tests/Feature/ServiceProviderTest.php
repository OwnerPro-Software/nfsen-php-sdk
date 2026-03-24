<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Facades\Nfsen;
use OwnerPro\Nfsen\NfseClient;
use OwnerPro\Nfsen\NfsenServiceProvider;

covers(NfsenServiceProvider::class, Nfsen::class);

it('resolves NfseClient from container', function () {
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
    ]);

    $client = app(NfseClient::class);
    expect($client)->toBeInstanceOf(NfseClient::class);
});

it('Nfsen facade resolves NfseClient', function () {
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
    ]);

    expect(Nfsen::getFacadeRoot())->toBeInstanceOf(NfseClient::class);
});

it('config nfsen is published', function () {
    expect(config('nfsen.ambiente'))->not->toBeNull();
});

it('configures client when cert path, senha and prefeitura are set', function () {
    $certPath = __DIR__.'/../fixtures/certs/fake.pfx';

    config([
        'nfsen.certificado.path' => $certPath,
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
    ]);

    // Re-resolve from container to trigger the configure() branch
    $client = app(NfseClient::class);

    // If configured, consultar() returns a ConsultsNfse without throwing
    expect($client->consultar())->toBeInstanceOf(ConsultsNfse::class);
});

it('facade emitir works directly when config is set', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_FACADE'], 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
    ]);

    $response = Nfsen::emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_FACADE');
})->with('dpsData');

it('facade for() returns configured NfseClient without double resolution', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_FOR'], 200)]);

    $client = Nfsen::for(makePfxContent(), 'secret', '9999999');

    expect($client)->toBeInstanceOf(NfseClient::class);
});

it('throws RuntimeException when cert file is empty', function () {
    $emptyFile = tempnam(sys_get_temp_dir(), 'nfse_test_');

    try {
        config([
            'nfsen.certificado.path' => $emptyFile,
            'nfsen.certificado.senha' => 'secret',
            'nfsen.prefeitura' => '3501608',
        ]);

        expect(fn () => app(NfseClient::class))
            ->toThrow(RuntimeException::class, 'Falha ao ler arquivo de certificado digital.');
    } finally {
        unlink($emptyFile);
    }
});

it('throws NfseException when cert config is incomplete', function () {
    expect(fn () => app(NfseClient::class))
        ->toThrow(NfseException::class, 'NfseClient não configurado');
});

it('throws NfseException when only certPath is missing', function () {
    config([
        'nfsen.certificado.path' => null,
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
    ]);

    expect(fn () => app(NfseClient::class))
        ->toThrow(NfseException::class, 'NfseClient não configurado');
});

it('throws NfseException when only senha is missing', function () {
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => '',
        'nfsen.prefeitura' => '3501608',
    ]);

    expect(fn () => app(NfseClient::class))
        ->toThrow(NfseException::class, 'NfseClient não configurado');
});

it('throws NfseException when only prefeitura is missing', function () {
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '',
    ]);

    expect(fn () => app(NfseClient::class))
        ->toThrow(NfseException::class, 'NfseClient não configurado');
});

it('throws NfseException when certPath does not exist as file', function () {
    config([
        'nfsen.certificado.path' => '/nonexistent/path/cert.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
    ]);

    expect(fn () => app(NfseClient::class))
        ->toThrow(NfseException::class, 'NfseClient não configurado');
});

it('casts integer prefeitura config to string', function () {
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => 3501608,
    ]);

    $client = app(NfseClient::class);
    expect($client)->toBeInstanceOf(NfseClient::class);
});

it('publishes config file in console', function () {
    $paths = ServiceProvider::pathsToPublish(
        NfsenServiceProvider::class,
        'nfsen-config',
    );

    expect($paths)->toBeArray()->not->toBeEmpty();

    $sourcePath = array_key_first($paths);
    expect($sourcePath)->toEndWith('config/nfsen.php');
    expect(file_exists($sourcePath))->toBeTrue();
    expect($paths[$sourcePath])->toContain('nfsen.php');
});
