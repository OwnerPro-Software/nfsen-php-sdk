<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\NfseClient;
use Pulsar\NfseNacional\Services\PrefeituraResolver;
use Pulsar\NfseNacional\Support\GzipCompressor;
use Pulsar\NfseNacional\Xml\Builders\CancelamentoBuilder;
use Pulsar\NfseNacional\Xml\Builders\SubstituicaoBuilder;
use Pulsar\NfseNacional\Xml\DpsBuilder;

it('substituir returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_OK'], 200)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $response = $client->substituir(
        $chave,
        $chaveSub,
        CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
        'Desenquadramento do Simples Nacional',
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe('CHAVE_OK');

    Http::assertSent(fn (Request $req) => str_contains($req->url(), $chave.'/eventos') &&
        $req->method() === 'POST' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('substituir returns rejection NfseResponse', function () {
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'NFSe não encontrada', 'codigo' => 'E404']]], 200)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $response = $client->substituir($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao');

    expect($response->sucesso)->toBeFalse();
    expect($response->erro)->toContain('não encontrada');
});

it('substituir throws NfseException when cert has no CNPJ nor CPF', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    expect(fn () => $client->substituir($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao'))
        ->toThrow(NfseException::class, 'Certificado não contém CNPJ nem CPF');
});

it('substituir throws HttpException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    expect(fn () => $client->substituir($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao'))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('substituir works without descricao', function () {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_OK'], 200)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $response = $client->substituir($chave, $chaveSub, CodigoJustificativaSubstituicao::EnquadramentoSimplesNacional);

    expect($response->sucesso)->toBeTrue();
});

it('substituir uses Americana custom URL without operation path', function () {
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE_AM'], 200)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '3501608');
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $client->substituir($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://americanahomologacao.nfe.com.br/api/adn/dps/recepcao' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('substituir throws NfseException when gzip compression fails', function () {
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

    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    expect(fn () => $client->substituir($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao'))
        ->toThrow(NfseException::class, 'comprimir XML');
});
