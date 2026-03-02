<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Enums\CodigoJustificativaCancelamento;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\NfseClient;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Support\GzipCompressor;
use Pulsar\NfseNacional\Xml\Builders\CancelamentoBuilder;
use Pulsar\NfseNacional\Xml\Builders\SubstituicaoBuilder;
use Pulsar\NfseNacional\Xml\DpsBuilder;

it('cancelar returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/cancelar_sucesso.json'), true),
        200
    )]);

    $pfx = file_get_contents(__DIR__.'/../fixtures/certs/fake-icpbr.pfx');
    $client = NfseClient::for($pfx, 'secret', '9999999');
    $response = $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('35016082026022700000000000000000000000000000000001');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/12345678901234567890123456789012345678901234567890/eventos' &&
        $req->method() === 'POST' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('cancelar accepts string codigoMotivo and coerces to enum', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/cancelar_sucesso.json'), true),
        200
    )]);

    $pfx = file_get_contents(__DIR__.'/../fixtures/certs/fake-icpbr.pfx');
    $client = NfseClient::for($pfx, 'secret', '9999999');
    $response = $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        '1',
        'Erro na emissao da nota fiscal'
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('35016082026022700000000000000000000000000000000001');
});

it('cancelar throws ValueError for invalid string codigoMotivo', function () {
    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        'INVALID',
        'Erro na emissao da nota fiscal'
    ))->toThrow(ValueError::class);
});

it('cancelar returns rejection NfseResponse on erro field', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/cancelar_rejeicao.json'), true),
        200
    )]);

    $pfx = file_get_contents(__DIR__.'/../fixtures/certs/fake-icpbr.pfx');
    $client = NfseClient::for($pfx, 'secret', '9999999');
    $response = $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    );

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toContain('não encontrada');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse/12345678901234567890123456789012345678901234567890/eventos' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('cancelar returns rejection NfseResponse on singular erro field', function () {
    Http::fake(['*' => Http::response(['erro' => 'Operação não permitida'], 200)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    );

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toBe('Operação não permitida');
});

it('cancelar throws NfseException when cert has no CNPJ nor CPF', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    ))->toThrow(NfseException::class, 'Certificado não contém CNPJ nem CPF');
});

it('cancelar throws HttpException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    ))->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('cancelar succeeds and reports error when event listener throws', function () {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/cancelar_sucesso.json'), true),
        200
    )]);

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

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $response = $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    );

    expect($response->sucesso)->toBeTrue();
    expect($reported)->toHaveCount(1);
    expect($reported[0])->toBeInstanceOf(\RuntimeException::class);
    expect($reported[0]->getMessage())->toBe('Listener exploded');
});

it('cancelar uses Americana custom URL without operation path', function () {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_AM'], 200)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '3501608');
    $client->cancelar('12345678901234567890123456789012345678901234567890', CodigoJustificativaCancelamento::ErroEmissao, 'Erro na emissao da nota fiscal');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://americanahomologacao.nfe.com.br/api/adn/dps/recepcao' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('cancelar uses Santa Ana de Parnaiba custom URL with operation path', function () {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_SP'], 200)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '3547304');
    $client->cancelar('12345678901234567890123456789012345678901234567890', CodigoJustificativaCancelamento::ErroEmissao, 'Erro na emissao da nota fiscal');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://producaorestrita.simplissweb.com.br/nfse/12345678901234567890123456789012345678901234567890/eventos' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('cancelar throws NfseException when gzip compression fails', function () {
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
        cancelamentoBuilder: new CancelamentoBuilder(makeXsdValidator()),
        substituicaoBuilder: new SubstituicaoBuilder(makeXsdValidator()),
        gzipCompressor: $compressor,
    );
    $client->configure(makeIcpBrPfxContent(), 'secret', '9999999');

    expect(fn () => $client->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal'
    ))->toThrow(NfseException::class, 'comprimir XML');
});
