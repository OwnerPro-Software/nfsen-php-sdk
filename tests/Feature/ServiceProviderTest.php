<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\Exceptions\RequestNotDeliveredException;
use OwnerPro\Nfsen\Facades\Nfsen;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\NfsenServiceProvider;

covers(NfsenServiceProvider::class, Nfsen::class);

it('resolves NfsenClient from container', function () {
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
    ]);

    $client = app(NfsenClient::class);
    expect($client)->toBeInstanceOf(NfsenClient::class);
});

it('Nfsen facade resolves NfsenClient', function () {
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
    ]);

    expect(Nfsen::getFacadeRoot())->toBeInstanceOf(NfsenClient::class);
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
    $client = app(NfsenClient::class);

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

it('facade for() returns configured NfsenClient without double resolution', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_FOR'], 200)]);

    $client = Nfsen::for(makePfxContent(), 'secret', '9999999');

    expect($client)->toBeInstanceOf(NfsenClient::class);
});

it('throws RuntimeException when cert file is empty', function () {
    $emptyFile = tempnam(sys_get_temp_dir(), 'nfse_test_');

    try {
        config([
            'nfsen.certificado.path' => $emptyFile,
            'nfsen.certificado.senha' => 'secret',
            'nfsen.prefeitura' => '3501608',
        ]);

        expect(fn () => app(NfsenClient::class))
            ->toThrow(RuntimeException::class, 'Falha ao ler arquivo de certificado digital.');
    } finally {
        unlink($emptyFile);
    }
});

it('throws NfseException when cert config is incomplete', function () {
    expect(fn () => app(NfsenClient::class))
        ->toThrow(NfseException::class, 'NfsenClient não configurado');
});

it('throws NfseException when only certPath is missing', function () {
    config([
        'nfsen.certificado.path' => null,
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
    ]);

    expect(fn () => app(NfsenClient::class))
        ->toThrow(NfseException::class, 'NfsenClient não configurado');
});

it('throws NfseException when only senha is missing', function () {
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => '',
        'nfsen.prefeitura' => '3501608',
    ]);

    expect(fn () => app(NfsenClient::class))
        ->toThrow(NfseException::class, 'NfsenClient não configurado');
});

it('throws NfseException when only prefeitura is missing', function () {
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '',
    ]);

    expect(fn () => app(NfsenClient::class))
        ->toThrow(NfseException::class, 'NfsenClient não configurado');
});

it('throws NfseException when certPath does not exist as file', function () {
    config([
        'nfsen.certificado.path' => '/nonexistent/path/cert.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
    ]);

    expect(fn () => app(NfsenClient::class))
        ->toThrow(NfseException::class, 'NfsenClient não configurado');
});

it('casts integer prefeitura config to string', function () {
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => 3501608,
    ]);

    $client = app(NfsenClient::class);
    expect($client)->toBeInstanceOf(NfsenClient::class);
});

it('SP ativa auto-danfse quando auto_danfse=true — emit retorna pdf', function (DpsData $data) {
    $xml = (string) file_get_contents(__DIR__.'/../fixtures/danfse/nfse-autorizada.xml');
    $gzip = base64_encode((string) gzencode($xml));

    Http::fake(['*' => Http::response([
        'chaveAcesso' => 'CHAVE_SP_AUTO',
        'nfseXmlGZipB64' => $gzip,
        'idDps' => 'DPS1',
        'tipoAmbiente' => 2,
        'versaoAplicativo' => '1.0',
        'dataHoraProcessamento' => '2026-04-15T10:00:00-03:00',
    ], 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
        'nfsen.validate_identity' => false,
        'nfsen.auto_danfse' => true,
    ]);

    $resp = app(NfsenClient::class)->emitir($data);

    expect($resp->pdf)->not->toBeNull();
    expect($resp->pdf)->toStartWith('%PDF-');
})->with('dpsData');

it('SP mantém o auto-render desligado quando a config não traz a chave', function (DpsData $data) {
    // Config publicado antes da 3.0.0 não tem `auto_danfse`. Sem este caso, nada exercita
    // o default do `??` pelo caminho do container.
    $xml = (string) file_get_contents(__DIR__.'/../fixtures/danfse/nfse-autorizada.xml');

    Http::fake(['*' => Http::response([
        'chaveAcesso' => 'CHAVE',
        'nfseXmlGZipB64' => base64_encode((string) gzencode($xml)),
    ], 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
        'nfsen.validate_identity' => false,
    ]);
    config()->offsetUnset('nfsen.auto_danfse');

    expect(app(NfsenClient::class)->emitir($data)->pdf)->toBeNull();
})->with('dpsData');

it('SP aceita a flag vinda de fonte que não tipa bool', function (DpsData $data) {
    // Config de banco ou YAML entrega `1`, não `true`. Sem o cast, o valor chega a um
    // parâmetro `bool` sob strict_types e vira TypeError na construção do client.
    $xml = (string) file_get_contents(__DIR__.'/../fixtures/danfse/nfse-autorizada.xml');

    Http::fake(['*' => Http::response([
        'chaveAcesso' => 'CHAVE',
        'nfseXmlGZipB64' => base64_encode((string) gzencode($xml)),
    ], 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
        'nfsen.validate_identity' => false,
        'nfsen.auto_danfse' => 1,
    ]);

    expect(app(NfsenClient::class)->emitir($data)->pdf)->toStartWith('%PDF-');
})->with('dpsData');

it('SP respeita auto_danfse=false — emit retorna pdf null', function (DpsData $data) {
    // A resposta traz XML de propósito: sem ele o PDF sairia null por falta de matéria
    // -prima, e o teste passaria mesmo com o auto-render ligado.
    $xml = (string) file_get_contents(__DIR__.'/../fixtures/danfse/nfse-autorizada.xml');

    Http::fake(['*' => Http::response([
        'chaveAcesso' => 'CHAVE',
        'nfseXmlGZipB64' => base64_encode((string) gzencode($xml)),
    ], 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
        'nfsen.validate_identity' => false,
        'nfsen.auto_danfse' => false,
    ]);

    $resp = app(NfsenClient::class)->emitir($data);

    expect($resp->pdf)->toBeNull();
})->with('dpsData');

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

/**
 * Falha de DNS: pré-envio comprovado. A classificação lê o errno do handler context
 * do Guzzle, nunca o texto da mensagem — forjar só a mensagem não distingue nada.
 */
function fakePreSendDnsFailure(): void
{
    Http::fake(['*' => function (): never {
        throw new ConnectionException('cURL error 6: Could not resolve host', 0, new ConnectException(
            'cURL error 6: Could not resolve host',
            new GuzzleRequest('GET', 'https://sefin.test/nfse'),
            null,
            ['errno' => 6],
        ));
    }]);
}

it('container binding classifies a pre-send failure as undelivered', function () {
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
    ]);
    fakePreSendDnsFailure();

    expect(fn () => app(NfsenClient::class)->consultar()->nfse(makeChaveAcesso()))
        ->toThrow(RequestNotDeliveredException::class);
});
