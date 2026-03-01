<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\NfseClient;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Support\GzipCompressor;
use Pulsar\NfseNacional\Xml\DpsBuilder;

// makePfxContent() definida em tests/helpers.php (criado na Task 8)

it('emitir returns success NfseResponse', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/emitir_sucesso.json'), true),
        200
    )]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->not->toBeNull();

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse' &&
        $req->method() === 'POST' &&
        isset($req['dpsXmlGZipB64'])
    );
})->with('dpsData');

it('consultar()->nfse returns success NfseResponse', function () {
    $xmlB64 = base64_encode(gzencode('<NFSe/>'));
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => $xmlB64], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->nfse('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toContain('<NFSe');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/CHAVE123' &&
        $req->method() === 'GET'
    );
});

it('consultar()->dps returns success NfseResponse with dpsXmlGZipB64', function () {
    $xmlB64 = base64_encode(gzencode('<DPS/>'));
    Http::fake(['*' => Http::response(['dpsXmlGZipB64' => $xmlB64], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->dps('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toContain('<DPS');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/dps/CHAVE123' &&
        $req->method() === 'GET'
    );
});

it('consultar()->dps returns null xml when response has no gzip key', function () {
    Http::fake(['*' => Http::response(['dps' => 'dados'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->dps('CHAVE123');

    expect($response->sucesso)->toBeTrue();
    expect($response->xml)->toBeNull();
});

it('throws InvalidArgumentException for invalid IBGE code', function () {
    expect(fn () => NfseClient::for(makePfxContent(), 'secret', '123'))
        ->toThrow(\InvalidArgumentException::class, 'IBGE');
});

it('forStandalone creates client without Laravel container', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_STANDALONE'], 200)]);

    $client = NfseClient::forStandalone(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_STANDALONE');
})->with('dpsData');

it('emitir returns rejection with erros array', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/emitir_rejeicao.json'), true),
        200
    )]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toContain('CNPJ');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse' &&
        isset($req['dpsXmlGZipB64'])
    );
})->with('dpsData');

it('emitir throws HttpException on server error', function (DpsData $data) {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->emitir($data))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
})->with('dpsData');

it('throws NfseException when not configured', function (DpsData $data) {
    $resolver = new \Pulsar\NfseNacional\Services\PrefeituraResolver(__DIR__.'/../../storage/prefeituras.json');
    $dpsBuilder = new \Pulsar\NfseNacional\Xml\DpsBuilder(makeXsdValidator());

    $client = new NfseClient(
        ambiente: \Pulsar\NfseNacional\Enums\NfseAmbiente::HOMOLOGACAO,
        timeout: 30,
        signingAlgorithm: 'sha1',
        sslVerify: true,
        prefeituraResolver: $resolver,
        dpsBuilder: $dpsBuilder,
        cancelamentoBuilder: new \Pulsar\NfseNacional\Xml\Builders\CancelamentoBuilder(makeXsdValidator()),
        substituicaoBuilder: new \Pulsar\NfseNacional\Xml\Builders\SubstituicaoBuilder(makeXsdValidator()),
    );

    expect(fn () => $client->emitir($data))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\NfseException::class, 'não configurado');
})->with('dpsData');

it('for() falls back to forStandalone when container has no binding', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'STANDALONE_CHAVE'], 200)]);

    // Temporarily remove the binding
    app()->offsetUnset(NfseClient::class);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('STANDALONE_CHAVE');
})->with('dpsData');

it('emitir succeeds and reports error when event listener throws', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_OK'], 200)]);

    $reported = [];
    $this->app->bind(\Illuminate\Contracts\Debug\ExceptionHandler::class, function () use (&$reported) {
        return new class($reported) extends \Illuminate\Foundation\Exceptions\Handler
        {
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

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($reported)->toHaveCount(1);
    expect($reported[0])->toBeInstanceOf(\RuntimeException::class);
    expect($reported[0]->getMessage())->toBe('Listener exploded');
})->with('dpsData');

it('emitir uses Americana custom URL without operation path', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_AM'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $client->emitir($data);

    Http::assertSent(fn (Request $req) => $req->url() === 'https://americanahomologacao.nfe.com.br/api/adn/dps/recepcao' &&
        isset($req['dpsXmlGZipB64'])
    );
})->with('dpsData');

it('emitir uses Santa Ana de Parnaiba custom URL with operation path', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_SP'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3547304');
    $client->emitir($data);

    Http::assertSent(fn (Request $req) => $req->url() === 'https://producaorestrita.simplissweb.com.br/nfse' &&
        isset($req['dpsXmlGZipB64'])
    );
})->with('dpsData');

it('emitir returns rejection with singular erro field', function (DpsData $data) {
    Http::fake(['*' => Http::response(['erro' => 'Erro genérico na emissão'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toBe('Erro genérico na emissão');
})->with('dpsData');

it('emitir uses producao URL when ambiente is PRODUCAO', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_PROD'], 200)]);

    $client = NfseClient::forStandalone(
        makePfxContent(), 'secret', '9999999',
        ambiente: \Pulsar\NfseNacional\Enums\NfseAmbiente::PRODUCAO,
    );
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_PROD');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.nfse.gov.br/sefinnacional/nfse' &&
        $req->method() === 'POST'
    );
})->with('dpsData');

it('emitir validates XML against XSD before sending', function () {
    Http::fake(['*' => Http::response(['chNFSe' => 'SHOULD_NOT_REACH'], 200)]);

    $data = new DpsData(
        infDps: makeInfDps(['tpemit' => 99]), // invalid per XSD (expects 1-3)
        prestador: makePrestadorCnpj(),
        tomador: new stdClass,
        servico: makeServicoMinimo(),
        valores: new stdClass,
    );

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->emitir($data))
        ->toThrow(NfseException::class, 'XML inválido');

    Http::assertNothingSent();
});

it('emitir throws NfseException when gzip compression fails', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chNFSe' => 'X'], 200)]);

    $compressor = Mockery::mock(GzipCompressor::class);
    $compressor->shouldReceive('__invoke')->andReturn(false);

    $client = new NfseClient(
        ambiente: NfseAmbiente::HOMOLOGACAO,
        timeout: 30,
        signingAlgorithm: 'sha1',
        sslVerify: true,
        prefeituraResolver: new PrefeituraResolver(__DIR__.'/../../storage/prefeituras.json'),
        dpsBuilder: new DpsBuilder(makeXsdValidator()),
        cancelamentoBuilder: new \Pulsar\NfseNacional\Xml\Builders\CancelamentoBuilder(makeXsdValidator()),
        substituicaoBuilder: new \Pulsar\NfseNacional\Xml\Builders\SubstituicaoBuilder(makeXsdValidator()),
        gzipCompressor: $compressor,
    );
    $client->configure(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->emitir($data))
        ->toThrow(NfseException::class, 'comprimir XML');
})->with('dpsData');
