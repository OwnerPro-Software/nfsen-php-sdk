<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Events\NfseEmitted;
use OwnerPro\Nfsen\Events\NfseFailed;
use OwnerPro\Nfsen\Events\NfseRejected;
use OwnerPro\Nfsen\Events\NfseRequested;
use OwnerPro\Nfsen\NfseClient;
use OwnerPro\Nfsen\Operations\NfseEmitter;

covers(NfseClient::class, NfseEmitter::class);

it('emitirDecisaoJudicial returns success NfseResponse', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/emitir_sucesso.json'), true),
        201
    )]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitirDecisaoJudicial($data);

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->not->toBeNull();
    expect($response->xml)->toContain('<NFSe');
    expect($response->tipoAmbiente)->toBe(2);

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/decisao-judicial/nfse' &&
        $req->method() === 'POST' &&
        isset($req['xmlGZipB64'])
    );
})->with('dpsData');

it('emitirDecisaoJudicial returns rejection with erros array', function (DpsData $data) {
    Http::fake(['*' => Http::response(
        json_decode(file_get_contents(__DIR__.'/../fixtures/responses/emitir_rejeicao.json'), true),
        200
    )]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitirDecisaoJudicial($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toContain('CNPJ');
    expect($response->idDps)->toBe('DPS_ERR_001');
})->with('dpsData');

it('emitirDecisaoJudicial returns rejection when response has no chaveAcesso', function (DpsData $data) {
    Http::fake(['*' => Http::response(['status' => 'ok'], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitirDecisaoJudicial($data);

    expect($response->sucesso)->toBeFalse();
    expect($response->erros[0]->descricao)->toBe('Resposta da API não contém chaveAcesso.');
})->with('dpsData');

it('emitirDecisaoJudicial throws HttpException on server error', function (DpsData $data) {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->emitirDecisaoJudicial($data))
        ->toThrow(\OwnerPro\Nfsen\Exceptions\HttpException::class);
})->with('dpsData');

it('emitirDecisaoJudicial accepts array and coerces to DpsData', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_ARRAY'], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $response = $client->emitirDecisaoJudicial([
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

    Http::assertSent(fn (Request $req) => $req->url() === 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/decisao-judicial/nfse' &&
        isset($req['xmlGZipB64'])
    );
});

it('emitirDecisaoJudicial uses xmlGZipB64 payload key', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_DJ'], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->emitirDecisaoJudicial($data);

    Http::assertSent(fn (Request $req) => isset($req['xmlGZipB64']) && ! isset($req['dpsXmlGZipB64']));
})->with('dpsData');

it('emitirDecisaoJudicial posts to decisao-judicial/nfse URL', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_DJ'], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->emitirDecisaoJudicial($data);

    Http::assertSent(fn (Request $req) => str_contains($req->url(), 'decisao-judicial/nfse'));
})->with('dpsData');

it('dispatches NfseRequested and NfseEmitted on successful emitirDecisaoJudicial', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_DJ'], 201)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->emitirDecisaoJudicial($data);

    Event::assertDispatched(NfseRequested::class, fn (NfseRequested $e) => $e->operacao === 'emitir_decisao_judicial');
    Event::assertDispatched(NfseEmitted::class);
})->with('dpsData');

it('dispatches NfseRejected on emitirDecisaoJudicial rejection', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'Erro', 'codigo' => 'E001']]], 200)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');
    $client->emitirDecisaoJudicial($data);

    Event::assertDispatched(NfseRejected::class, fn (NfseRejected $e) => $e->codigoErro === 'E001');
})->with('dpsData');

it('dispatches NfseFailed on emitirDecisaoJudicial HttpException', function (DpsData $data) {
    Event::fake();
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $client = NfseClient::for(makePfxContent(), 'secret', '9999999');

    try {
        $client->emitirDecisaoJudicial($data);
    } catch (\OwnerPro\Nfsen\Exceptions\HttpException) {
        // expected
    }

    Event::assertDispatched(NfseRequested::class);
    Event::assertDispatched(NfseFailed::class, fn (NfseFailed $e) => $e->operacao === 'emitir_decisao_judicial');
})->with('dpsData');
