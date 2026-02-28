<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\Events\NfseCancelled;
use Pulsar\NfseNacional\Events\NfseEmitted;
use Pulsar\NfseNacional\Events\NfseFailed;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Events\NfseRejected;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\NfseClient;

it('dispatches NfseRequested and NfseEmitted on successful emitir', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE123'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->emitir($data);

    Event::assertDispatched(NfseRequested::class);
    Event::assertDispatched(NfseEmitted::class);
})->with('dpsData');

it('dispatches NfseCancelled on successful cancelar', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE123'], 200)]);

    $pfx    = file_get_contents(__DIR__ . '/../fixtures/certs/fake-icpbr.pfx');
    $client = NfseClient::for($pfx, 'secret', '9999999');
    $client->cancelar('CHAVE50CARACTERES1234567890123456789012345678901', MotivoCancelamento::ErroEmissao, 'Erro');

    Event::assertDispatched(NfseCancelled::class);
});

it('dispatches NfseQueried on successful consultar', function () {
    Event::fake();
    $xmlB64 = base64_encode(gzencode('<NFSe/>'));
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => $xmlB64], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->consultar()->nfse('CHAVE123');

    Event::assertDispatched(NfseQueried::class);
});

it('dispatches NfseRejected on emitir rejection', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'Erro', 'codigo' => 'E001']]], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->emitir($data);

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e) => $e->codigoErro === 'E001');
})->with('dpsData');

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
