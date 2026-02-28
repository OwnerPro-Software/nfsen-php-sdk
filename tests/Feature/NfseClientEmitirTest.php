<?php

use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\NfseClient;

// makePfxContent() definida em tests/helpers.php (criado na Task 8)

it('emitir returns success NfseResponse', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/emitir_sucesso.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->not->toBeNull();
})->with('dpsData');

it('emitir returns rejection NfseResponse on erro field', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/emitir_rejeicao.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->not->toBeNull();
})->with('dpsData');

it('consultar()->nfse returns success NfseResponse', function () {
    $xmlB64 = base64_encode(gzencode('<NFSe/>'));
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => $xmlB64], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->nfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toContain('<NFSe');
});

it('consultar()->dps returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(['dps' => 'dados'], 200)]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->consultar()->dps('CHAVE123');

    expect($response->sucesso)->toBeTrue();
});

it('throws InvalidArgumentException for invalid IBGE code', function () {
    expect(fn () => NfseClient::for(makePfxContent(), 'secret', '123'))
        ->toThrow(\InvalidArgumentException::class, 'IBGE');
});

it('forStandalone creates client without Laravel container', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_STANDALONE'], 200)]);

    $client = NfseClient::forStandalone(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_STANDALONE');
})->with('dpsData');

it('emitir returns rejection with erros array', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__ . '/../fixtures/responses/emitir_rejeicao.json'), true),
        200
    )]);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toContain('CNPJ');
})->with('dpsData');

it('emitir throws HttpException on server error', function (DpsData $data) {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');

    expect(fn () => $client->emitir($data))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
})->with('dpsData');

it('throws NfseException when not configured', function (DpsData $data) {
    $resolver   = new \Pulsar\NfseNacional\Services\PrefeituraResolver(__DIR__ . '/../../storage/prefeituras.json');
    $dpsBuilder = new \Pulsar\NfseNacional\Xml\DpsBuilder(__DIR__ . '/../../storage/schemes');

    $client = new NfseClient(
        ambiente:           \Pulsar\NfseNacional\Enums\NfseAmbiente::HOMOLOGACAO,
        timeout:            30,
        signingAlgorithm:   'sha1',
        sslVerify:          true,
        prefeituraResolver: $resolver,
        dpsBuilder:         $dpsBuilder,
    );

    expect(fn () => $client->emitir($data))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\NfseException::class, 'não configurado');
})->with('dpsData');

it('for() falls back to forStandalone when container has no binding', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'STANDALONE_CHAVE'], 200)]);

    // Temporarily remove the binding
    app()->offsetUnset(NfseClient::class);

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('STANDALONE_CHAVE');
})->with('dpsData');

it('emitir succeeds and reports error when event listener throws', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_OK'], 200)]);

    $reported = [];
    $this->app->bind(\Illuminate\Contracts\Debug\ExceptionHandler::class, function () use (&$reported) {
        return new class($reported) extends \Illuminate\Foundation\Exceptions\Handler {
            /** @param list<Throwable> $reported */
            public function __construct(private array &$reported)
            {
                parent::__construct(app());
            }

            public function report(Throwable $e): void
            {
                $this->reported[] = $e;
            }
        };
    });

    \Illuminate\Support\Facades\Event::listen(
        \Pulsar\NfseNacional\Events\NfseRequested::class,
        function (): never {
            throw new \RuntimeException('Listener exploded');
        }
    );

    $client   = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($reported)->toHaveCount(1);
    expect($reported[0])->toBeInstanceOf(\RuntimeException::class);
    expect($reported[0]->getMessage())->toBe('Listener exploded');
})->with('dpsData');
