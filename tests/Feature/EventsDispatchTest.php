<?php

covers(
    \Pulsar\NfseNacional\Operations\NfseCanceller::class,
    \Pulsar\NfseNacional\Operations\NfseEmitter::class,
    \Pulsar\NfseNacional\Operations\NfseSubstitutor::class,
);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Dps\DTO\DpsData;
use Pulsar\NfseNacional\Enums\CodigoJustificativaCancelamento;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Events\NfseCancelled;
use Pulsar\NfseNacional\Events\NfseEmitted;
use Pulsar\NfseNacional\Events\NfseFailed;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Events\NfseRejected;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\Events\NfseSubstituted;
use Pulsar\NfseNacional\NfseClient;

it('dispatches NfseRequested and NfseEmitted on successful emitir', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE123'], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->emitir($data);

    Event::assertDispatched(NfseRequested::class);
    Event::assertDispatched(NfseEmitted::class);
})->with('dpsData');

it('dispatches NfseCancelled on successful cancelar', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201)]);

    $pfx = file_get_contents(__DIR__.'/../fixtures/certs/fake-icpbr.pfx');
    $client = NfseClient::for($pfx, 'secret', '9999999');
    $chave = '12345678901234567890123456789012345678901234567890';
    $client->cancelar($chave, CodigoJustificativaCancelamento::ErroEmissao, 'Erro na emissao da nota fiscal');

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'cancelar' && $e->metadata === ['chave' => $chave]);
    Event::assertDispatched(NfseCancelled::class);
});

it('dispatches NfseEmitted and NfseSubstituted on successful substituir', function (DpsData $data) {
    Event::fake();
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fakeSequence()
        ->push(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)
        ->push(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $client->substituir($chave, $data, CodigoJustificativaSubstituicao::DesenquadramentoSimplesNacional, 'Desenquadramento do Simples Nacional');

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'emitir');
    Event::assertDispatched(NfseEmitted::class);
    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'substituir' && $e->metadata === ['chave' => $chave]);
    Event::assertDispatched(NfseSubstituted::class, fn (NfseSubstituted $e) => $e->chave === $chave && $e->chaveSubstituta === $chaveSub);
    Event::assertNotDispatched(NfseCancelled::class);
})->with('dpsData');

it('dispatches NfseQueried on successful consultar', function () {
    Event::fake();
    $xmlB64 = base64_encode(gzencode('<NFSe/>'));
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => $xmlB64], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->consultar()->nfse(makeChaveAcesso());

    Event::assertDispatched(NfseQueried::class);
});

it('dispatches NfseRejected on emitir when response has no chaveAcesso', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response(['status' => 'ok'], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->emitir($data);

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'emitir');
    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e) => $e->codigoErro === 'SEM_CHAVE');
    Event::assertNotDispatched(NfseEmitted::class);
})->with('dpsData');

it('dispatches NfseRejected on emitir rejection', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'Erro', 'codigo' => 'E001']]], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->emitir($data);

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e) => $e->codigoErro === 'E001');
})->with('dpsData');

it('dispatches NfseRejected on cancelar rejection', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['erro' => ['descricao' => 'NFSe não encontrada', 'codigo' => 'E404']], 200)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $client->cancelar('12345678901234567890123456789012345678901234567890', CodigoJustificativaCancelamento::ErroEmissao, 'Erro na emissao da nota fiscal');

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'cancelar');
    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e) => $e->codigoErro === 'E404');
});

it('dispatches NfseRejected on substituir event rejection', function (DpsData $data) {
    Event::fake();
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fakeSequence()
        ->push(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)
        ->push(['erro' => ['descricao' => 'NFSe não encontrada', 'codigo' => 'E404']], 200);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $client->substituir($chave, $data, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao');

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'emitir');
    Event::assertDispatched(NfseEmitted::class);
    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'substituir' && $e->metadata === ['chave' => $chave]);
    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e) => $e->codigoErro === 'E404');
    Event::assertNotDispatched(NfseSubstituted::class);
})->with('dpsData');

it('dispatches NfseSubstituted on successful confirmarSubstituicao', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $client->confirmarSubstituicao($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao');

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'substituir');
    Event::assertDispatched(NfseSubstituted::class, fn (NfseSubstituted $e) => $e->chave === $chave && $e->chaveSubstituta === $chaveSub);
    Event::assertNotDispatched(NfseEmitted::class);
});

it('dispatches NfseRejected on confirmarSubstituicao rejection', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['erro' => ['descricao' => 'NFSe não encontrada', 'codigo' => 'E404']], 200)]);

    $client = NfseClient::for(makeIcpBrPfxContent(), 'secret', '9999999');
    $chave = makeChaveAcesso();
    $chaveSub = '98765432109876543210987654321098765432109876543210';
    $client->confirmarSubstituicao($chave, $chaveSub, CodigoJustificativaSubstituicao::Outros, 'Outro motivo para substituicao');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e) => $e->codigoErro === 'E404');
    Event::assertNotDispatched(NfseSubstituted::class);
});

it('dispatches NfseRequested and NfseQueried on consultar danfse', function () {
    Event::fake();
    Http::fake(['*' => Http::response('PDF-CONTENT', 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->consultar()->danfse(makeChaveAcesso());

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'consultar');
    Event::assertDispatched(NfseQueried::class);
});

it('dispatches NfseFailed on consultar HttpException', function () {
    Event::fake();
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    try {
        $client->consultar()->nfse(makeChaveAcesso());
    } catch (\Pulsar\NfseNacional\Exceptions\HttpException) {
        // expected
    }

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'consultar');
    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e) => $e->operacao === 'consultar');
});

it('dispatches NfseFailed on consultar danfse HTTP error', function () {
    Event::fake();
    Http::fake(['*' => Http::response('Not Found', 404)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->consultar()->danfse(makeChaveAcesso());

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'consultar');
    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e) => $e->operacao === 'consultar');
    Event::assertNotDispatched(NfseQueried::class);
});

it('dispatches NfseRejected on consultar eventos rejection', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['erro' => ['descricao' => 'Eventos não encontrados', 'codigo' => 'E404']], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->consultar()->eventos(makeChaveAcesso());

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e) => $e->codigoErro === 'E404');
    Event::assertNotDispatched(NfseQueried::class);
});

it('dispatches NfseRejected on consultar dps with singular erro field', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['erro' => ['descricao' => 'Erro genérico', 'codigo' => 'E999']], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->consultar()->dps('DPS123');

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e) => $e->codigoErro === 'E999');
    Event::assertNotDispatched(NfseQueried::class);
});

it('dispatches NfseFailed on emitir NfseException', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response(['chaveAcesso' => 'X'], 200)]);

    $compressor = Mockery::mock(\Pulsar\NfseNacional\Support\GzipCompressor::class);
    $compressor->shouldReceive('__invoke')->andReturn(false);

    $client = makeNfseClient(gzipCompressor: $compressor);

    try {
        $client->emitir($data);
    } catch (\Pulsar\NfseNacional\Exceptions\NfseException) {
        // expected
    }

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'emitir');
    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e) => $e->operacao === 'emitir');
})->with('dpsData');

it('dispatches NfseFailed on cancelar NfseException', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['chaveAcesso' => 'X'], 200)]);

    $compressor = Mockery::mock(\Pulsar\NfseNacional\Support\GzipCompressor::class);
    $compressor->shouldReceive('__invoke')->andReturn(false);

    $client = makeNfseClient(gzipCompressor: $compressor, pfxContent: makeIcpBrPfxContent());

    try {
        $client->cancelar('12345678901234567890123456789012345678901234567890', CodigoJustificativaCancelamento::ErroEmissao, 'Erro na emissao da nota fiscal');
    } catch (\Pulsar\NfseNacional\Exceptions\NfseException) {
        // expected
    }

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'cancelar');
    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e) => $e->operacao === 'cancelar');
});

it('dispatches NfseFailed on consultar NfseException', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => '!!!invalid-base64!!!'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    try {
        $client->consultar()->nfse(makeChaveAcesso());
    } catch (\Pulsar\NfseNacional\Exceptions\NfseException) {
        // expected
    }

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'consultar');
    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e) => $e->operacao === 'consultar');
});

it('dispatches NfseFailed on consultar dps non-HTTP exception', function () {
    Event::fake();
    Http::fake(['*' => function (): never {
        throw new \RuntimeException('Connection reset');
    }]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    try {
        $client->consultar()->dps('DPS123');
    } catch (\RuntimeException) {
        // expected
    }

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'consultar');
    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e) => $e->operacao === 'consultar');
});

it('dispatches NfseFailed on emitir HttpException', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    try {
        $client->emitir($data);
    } catch (\Pulsar\NfseNacional\Exceptions\HttpException) {
        // expected
    }

    Event::assertDispatched(NfseRequested::class);
    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e) => $e->operacao === 'emitir');
})->with('dpsData');
