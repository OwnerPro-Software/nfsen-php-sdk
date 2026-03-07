<?php

covers(\Pulsar\NfseNacional\NfseClient::class, \Pulsar\NfseNacional\Operations\NfseSubstitutor::class);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Exceptions\NfseException;
use Pulsar\NfseNacional\NfseClient;
use Pulsar\NfseNacional\Support\GzipCompressor;

it('confirmarSubstituicao returns success NfseResponse', function () {
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $response = $client->confirmarSubstituicao(
        $chave,
        $chaveSub,
        CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional,
        'Desenquadramento do Simples Nacional',
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe($chave);
    expect($response->xml)->not->toBeNull();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $req) => str_contains($req->url(), $chave.'/eventos') &&
        $req->method() === 'POST' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('confirmarSubstituicao returns rejection NfseResponse', function () {
    Http::fake(['*' => Http::response(['erro' => ['descricao' => 'NFSe não encontrada', 'codigo' => 'E404']], 200)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $response = $client->confirmarSubstituicao($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao');

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toContain('não encontrada');
});

it('confirmarSubstituicao accepts string codigoMotivo and coerces to enum', function () {
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $response = $client->confirmarSubstituicao($chave, $chaveSub, '01', 'Desenquadramento do Simples Nacional');

    expect($response->sucesso)->toBeTrue();
});

it('confirmarSubstituicao throws ValueError for invalid string codigoMotivo', function () {
    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    expect(fn () => $client->confirmarSubstituicao($chave, $chaveSub, 'INVALID', 'Motivo'))
        ->toThrow(ValueError::class);
});

it('confirmarSubstituicao throws InvalidArgumentException for invalid chaveSubstituida', function () {
    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    expect(fn () => $client->confirmarSubstituicao('INVALID_CHAVE', $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo'))
        ->toThrow(\InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('confirmarSubstituicao throws InvalidArgumentException for invalid chaveSubstituta', function () {
    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();

    expect(fn () => $client->confirmarSubstituicao($chave, 'INVALID_CHAVE_SUB', CodigoJustificativaSubstituicao::Outros, 'Outro motivo'))
        ->toThrow(\InvalidArgumentException::class, 'chaveAcesso inválida');
});

it('confirmarSubstituicao throws NfseException when cert has no CNPJ nor CPF', function () {
    Http::fake(['*' => Http::response([], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    expect(fn () => $client->confirmarSubstituicao($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao'))
        ->toThrow(NfseException::class, 'Certificado não contém CNPJ nem CPF');
});

it('confirmarSubstituicao throws HttpException on server error', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    expect(fn () => $client->confirmarSubstituicao($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao'))
        ->toThrow(\Pulsar\NfseNacional\Exceptions\HttpException::class);
});

it('confirmarSubstituicao sends XML without xMotivo when descricao is null', function () {
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $response = $client->confirmarSubstituicao($chave, $chaveSub, CodigoJustificativaSubstituicao::EnquadramentoSimplesNacional);

    expect($response->sucesso)->toBeTrue();

    Http::assertSent(function (Request $req) {
        $xml = gzdecode(base64_decode($req['pedidoRegistroEventoXmlGZipB64']));

        return ! str_contains($xml, '<xMotivo>');
    });
});

it('confirmarSubstituicao uses Americana custom URL', function () {
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '3501608');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $client->confirmarSubstituicao($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao');

    Http::assertSent(fn (Request $req) => $req->url() === 'https://americanahomologacao.nfe.com.br/api/adn/dps/recepcao' &&
        isset($req['pedidoRegistroEventoXmlGZipB64'])
    );
});

it('confirmarSubstituicao throws NfseException when gzip compression fails', function () {
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 200)]);

    $compressor = Mockery::mock(GzipCompressor::class);
    $compressor->shouldReceive('__invoke')->andReturn(false);

    $client = makeNfseClient(gzipCompressor: $compressor, pfxContent: makeIcpBrPfxContent());

    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    expect(fn () => $client->confirmarSubstituicao($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao'))
        ->toThrow(NfseException::class, 'comprimir XML');
});
