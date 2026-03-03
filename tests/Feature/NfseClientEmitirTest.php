<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\NfseClient;
use Pulsar\NfseNacional\Support\GzipCompressor;

// makePfxContent() definida em tests/helpers.php (criado na Task 8)

it('emitir returns success NfseResponse', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/emitir_sucesso.json'), true),
        201
    )]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->not->toBeNull();
    expect($response->xml)->toContain('<NFSe');
    expect($response->idDps)->toBe('DPS001');
    expect($response->alertas)->not->toBeEmpty();
    expect($response->tipoAmbiente)->toBe(2);
    expect($response->versaoAplicativo)->toBe('1.0.0');
    expect($response->dataHoraProcessamento)->toBe('2026-03-02T12:00:00-03:00');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse' &&
        $req->method() === 'POST' &&
        isset($req['dpsXmlGZipB64'])
    );
})->with('dpsData');

it('consultar()->nfse returns success NfseResponse', function () {
    $chave = makeChaveAcesso();
    $xmlB64 = base64_encode(gzencode('<NFSe/>'));
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => $xmlB64, 'chaveAcesso' => $chave], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->nfse($chave);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe($chave);
    expect($response->xml)->toContain('<NFSe');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/'.$chave &&
        $req->method() === 'GET'
    );
});

it('consultar()->dps returns success NfseResponse with chaveAcesso', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_DPS_OK', 'idDps' => 'DPS001'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->dps('DPS123');

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_DPS_OK');
    expect($response->xml)->toBeNull();

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/dps/DPS123' &&
        $req->method() === 'GET'
    );
});

it('consultar()->dps returns null chave when response has no chaveAcesso', function () {
    Http::fake(['*' => Http::response(['idDps' => 'DPS001'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->consultar()->dps('DPS123');

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBeNull();
});

it('throws InvalidArgumentException for invalid IBGE code', function () {
    expect(fn () => NfseClient::for(makePfxContent(), 'secret', '123'))
        ->toThrow(\InvalidArgumentException::class, 'IBGE');
});

it('forStandalone creates client without Laravel container', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_STANDALONE'], 201)]);

    $client = NfseClient::forStandalone(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_STANDALONE');
})->with('dpsData');

it('for() falls back to forStandalone when config is null', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'STANDALONE_CHAVE'], 201)]);

    config()->offsetUnset('nfse-nacional');

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('STANDALONE_CHAVE');
})->with('dpsData');

it('emitir returns rejection with erros array', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/emitir_rejeicao.json'), true),
        200
    )]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros)->toBeArray();
    expect($response->erros[0])->toBeInstanceOf(\Pulsar\NfseNacional\Responses\MensagemProcessamento::class);
    expect($response->erros[0]->descricao)->toContain('CNPJ');
    expect($response->idDps)->toBe('DPS_ERR_001');
    expect($response->tipoAmbiente)->toBe(2);
    expect($response->versaoAplicativo)->toBe('1.0.0');
    expect($response->dataHoraProcessamento)->toBe('2026-03-02T12:00:00-03:00');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse' &&
        isset($req['dpsXmlGZipB64'])
    );
})->with('dpsData');

it('emitir returns rejection on 4xx response with erro body', function (DpsData $data) {
    Http::fake(['*' => Http::response(['erro' => ['descricao' => 'Certificado inválido', 'codigo' => 'E401']], 401)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toBe('Certificado inválido');
})->with('dpsData');

it('emitir throws HttpException on server error', function (DpsData $data) {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->emitir($data))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
})->with('dpsData');

it('emitir succeeds and reports error when event listener throws', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_OK'], 201)]);

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
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_AM'], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $client->emitir($data);

    Http::assertSent(fn (Request $req) => $req->url() === 'https://americanahomologacao.nfe.com.br/api/adn/dps/recepcao' &&
        isset($req['dpsXmlGZipB64'])
    );
})->with('dpsData');

it('emitir uses Santa Ana de Parnaiba custom URL with operation path', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_SP'], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3547304');
    $client->emitir($data);

    Http::assertSent(fn (Request $req) => $req->url() === 'https://producaorestrita.simplissweb.com.br/nfse' &&
        isset($req['dpsXmlGZipB64'])
    );
})->with('dpsData');

it('emitir returns rejection when response has no chaveAcesso', function (DpsData $data) {
    Http::fake(['*' => Http::response(['status' => 'ok'], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->chave)->toBeNull();
    expect($response->erros[0]->descricao)->toBe('Resposta da API não contém chaveAcesso.');
})->with('dpsData');

it('emitir returns rejection with singular erro field', function (DpsData $data) {
    Http::fake(['*' => Http::response(['erro' => ['descricao' => 'Erro genérico na emissão', 'codigo' => 'E999']], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toBe('Erro genérico na emissão');
})->with('dpsData');

it('emitir uses producao URL when ambiente is PRODUCAO', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_PROD'], 201)]);

    $client = NfseClient::forStandalone(
        makePfxContent(), 'secret', '9999999',
        ambiente: \Pulsar\NfseNacional\Enums\NfseAmbiente::PRODUCAO,
    );
    $response = $client->emitir($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_PROD');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.nfse.gov.br/SefinNacional/nfse' &&
        $req->method() === 'POST'
    );
})->with('dpsData');

it('configure enforces sslVerify true when ambiente is PRODUCAO even if config says false', function () {
    $client = NfseClient::forStandalone(
        makePfxContent(), 'secret', '9999999',
        ambiente: NfseAmbiente::PRODUCAO,
        sslVerify: false,
    );

    $consulter = (new ReflectionProperty($client, 'consulter'))->getValue($client);
    $queryExecutor = (new ReflectionProperty($consulter, 'client'))->getValue($consulter);
    $httpClient = (new ReflectionProperty($queryExecutor, 'httpClient'))->getValue($queryExecutor);
    $sslVerify = (new ReflectionProperty($httpClient, 'sslVerify'))->getValue($httpClient);

    expect($sslVerify)->toBeTrue();
});

it('emitir accepts array and coerces to DpsData', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_ARRAY'], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitir([
        'infDPS' => [
            'tpAmb' => '2',
            'dhEmi' => '2026-02-27T10:00:00-03:00',
            'verAplic' => '1.0',
            'serie' => '1',
            'nDPS' => '1',
            'dCompet' => '2026-02-27',
            'tpEmit' => '1',
            'cLocEmi' => '3501608',
        ],
        'prest' => [
            'CNPJ' => '12345678000195',
            'regTrib' => [
                'opSimpNac' => '1',
                'regEspTrib' => '0',
            ],
            'xNome' => 'Empresa',
        ],
        'serv' => [
            'cServ' => [
                'cTribNac' => '010101',
                'xDescServ' => 'Serviço de Teste',
                'cNBS' => '123456789',
            ],
            'cLocPrestacao' => '3501608',
        ],
        'valores' => [
            'vServPrest' => ['vServ' => '100.00'],
            'trib' => [
                'tribMun' => [
                    'tribISSQN' => '1',
                    'tpRetISSQN' => '1',
                ],
                'indTotTrib' => '0',
            ],
        ],
    ]);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_ARRAY');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse' &&
        isset($req['dpsXmlGZipB64'])
    );
});

it('emitir throws when array is missing required keys', function () {
    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->emitir(['infDPS' => []]))
        ->toThrow(\ErrorException::class);
});

it('emitir validates XML against XSD before sending', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'SHOULD_NOT_REACH'], 201)]);

    $servico = new \Pulsar\NfseNacional\Dps\DTO\Servico\Servico(
        cServ: new \Pulsar\NfseNacional\Dps\DTO\Servico\CodigoServico(
            cTribNac: 'INVALID_LONG_VALUE_THAT_WILL_FAIL_XSD',
            xDescServ: 'Serviço',
            cNBS: '123456789',
        ),
        cLocPrestacao: '3501608',
    );

    $data = new DpsData(
        infDPS: makeInfDps(),
        subst: null,
        prest: makePrestadorCnpj(),
        toma: null,
        interm: null,
        serv: $servico,
        valores: makeValoresMinimo(),
    );

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->emitir($data))
        ->toThrow(NfseException::class, 'XML inválido');

    Http::assertNothingSent();
});

it('emitir throws NfseException on invalid base64 in nfseXmlGZipB64', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE123', 'nfseXmlGZipB64' => '!!!invalid!!!'], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->emitir($data))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\NfseException::class, 'base64');
})->with('dpsData');

it('emitir throws NfseException on invalid gzip in nfseXmlGZipB64', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE123', 'nfseXmlGZipB64' => base64_encode('not-gzip-data')], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->emitir($data))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\NfseException::class, 'descomprimir');
})->with('dpsData');

it('emitir throws NfseException when gzip compression fails', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'X'], 201)]);

    $compressor = Mockery::mock(GzipCompressor::class);
    $compressor->shouldReceive('__invoke')->andReturn(false);

    $client = makeNfseClient(gzipCompressor: $compressor);

    expect(fn () => $client->emitir($data))
        ->toThrow(NfseException::class, 'comprimir XML');
})->with('dpsData');
