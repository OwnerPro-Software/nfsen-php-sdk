<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use OwnerPro\Nfsen\Contracts\Driving\ConsultsNfse;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Exceptions\IndeterminateResultException;
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

it('SP ativa auto-danfse quando config.danfse.enabled=true — emit retorna pdf', function (DpsData $data) {
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
        'nfsen.danfse.enabled' => true,
        'nfsen.danfse.logo_path' => false,
        'nfsen.danfse.municipality' => null,
    ]);

    $resp = app(NfsenClient::class)->emitir($data);

    expect($resp->pdf)->not->toBeNull();
    expect($resp->pdf)->toStartWith('%PDF-');
})->with('dpsData');

it('SP não ativa auto-danfse quando config.danfse ausente — emit retorna pdf null', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE'], 201)]);

    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '3501608',
        'nfsen.validate_identity' => false,
        'nfsen.danfse' => null, // bloco ausente ⇒ isDanfseEnabled(null) = false ⇒ sem auto-render.
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

function configureContainerClient(bool $detectNotDelivered): void
{
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
        'nfsen.detect_not_delivered' => $detectNotDelivered,
    ]);
}

it('container binding honours detect_not_delivered, same as NfsenClient::for()', function () {
    // Os dois caminhos de construção precisam entregar o mesmo contrato de exceção:
    // o binding omitia a chave e caía no default false, então a mesma config dava
    // RequestNotDeliveredException via ::for() e IndeterminateResultException via
    // container — sem aviso, justamente a fragilidade que a flag existe para evitar.
    configureContainerClient(true);
    fakePreSendDnsFailure();

    expect(fn () => app(NfsenClient::class)->consultar()->nfse(makeChaveAcesso()))
        ->toThrow(RequestNotDeliveredException::class);
});

it('container binding keeps the 2.5.0 contract when detect_not_delivered is off', function () {
    configureContainerClient(false);
    fakePreSendDnsFailure();

    expect(fn () => app(NfsenClient::class)->consultar()->nfse(makeChaveAcesso()))
        ->toThrow(IndeterminateResultException::class);
});

it('container binding defaults to off when the config predates 2.6.0', function () {
    // Config publicado antes da 2.6.0 não tem a chave: `?? false` mantém o
    // comportamento antigo em vez de estourar com índice indefinido.
    config([
        'nfsen.certificado.path' => __DIR__.'/../fixtures/certs/fake.pfx',
        'nfsen.certificado.senha' => 'secret',
        'nfsen.prefeitura' => '9999999',
    ]);
    config()->offsetUnset('nfsen.detect_not_delivered');
    fakePreSendDnsFailure();

    expect(fn () => app(NfsenClient::class)->consultar()->nfse(makeChaveAcesso()))
        ->toThrow(IndeterminateResultException::class);
});
