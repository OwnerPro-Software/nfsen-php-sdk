<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Enums\MotivoCancelamento;
use Pulsar\NfseNacional\Events\NfseCancelled;
use Pulsar\NfseNacional\Events\NfseEmitted;
use Pulsar\NfseNacional\Events\NfseQueried;
use Pulsar\NfseNacional\Events\NfseRequested;
use Pulsar\NfseNacional\NfseClient;

it('dispatches NfseRequested and NfseEmitted on successful emitir', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE123'], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $client->emitir($data);

    Event::assertDispatched(NfseRequested::class);
    Event::assertDispatched(NfseEmitted::class);
})->with('dpsData');

it('dispatches NfseCancelled on successful cancelar', function () {
    Event::fake();
    Http::fake(['*' => Http::response(['chNFSe' => 'CHAVE123'], 200)]);

    $pfx    = file_get_contents(__DIR__ . '/../fixtures/certs/fake-icpbr.pfx');
    $client = NfseClient::for($pfx, 'secret', '3501608');
    $client->cancelar('CHAVE50CARACTERES1234567890123456789012345678901', MotivoCancelamento::ErroEmissao, 'Erro');

    Event::assertDispatched(NfseCancelled::class);
});

it('dispatches NfseQueried on successful consultar', function () {
    Event::fake();
    $xmlB64 = base64_encode(gzencode('<NFSe/>'));
    Http::fake(['*' => Http::response(['nfseXmlGZipB64' => $xmlB64], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '3501608');
    $client->consultar()->nfse('CHAVE123');

    Event::assertDispatched(NfseQueried::class);
});
