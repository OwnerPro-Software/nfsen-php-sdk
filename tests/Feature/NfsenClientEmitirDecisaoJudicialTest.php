<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Dps\DTO\DpsData;
use OwnerPro\Nfsen\Exceptions\NfseException;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Operations\NfseEmitter;

covers(NfsenClient::class, NfseEmitter::class);

const MENSAGEM_DECISAO_JUDICIAL = 'emitirDecisaoJudicial() não é suportado por este SDK. '
    .'O endpoint decisao-judicial/nfse recebe o documento NFS-e completo — '
    .'NFSeBypassPostRequest.xmlGZipB64 é "Documento XML da NFSe" (SefinNacional-swagger.json) —, '
    .'não uma DPS: em TCInfNFSe a DPS é apenas o último filho, ao lado de nNFSe, nDFSe, cStat, '
    .'dhProc e dos valores já apurados, e ambGer admite somente 1-Prefeitura ou 2-Sistema Nacional. '
    .'Emitir por decisão judicial cabe a quem gera a NFS-e.';

it('emitirDecisaoJudicial lança NfseException explicando por que a operação não existe', function (DpsData $data) {
    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    $mensagem = null;

    try {
        $client->emitirDecisaoJudicial($data);
    } catch (NfseException $e) {
        $mensagem = $e->getMessage();
    }

    expect($mensagem)->toBe(MENSAGEM_DECISAO_JUDICIAL);
})->with('dpsData');

it('emitirDecisaoJudicial não emite requisição alguma', function (DpsData $data) {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_DJ'], 201)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->emitirDecisaoJudicial($data))->toThrow(NfseException::class);

    Http::assertNothingSent();
})->with('dpsData');

it('emitirDecisaoJudicial não dispara evento algum', function (DpsData $data) {
    Event::fake();

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    expect(fn () => $client->emitirDecisaoJudicial($data))->toThrow(NfseException::class);

    Event::assertNothingDispatched();
})->with('dpsData');

it('emitirDecisaoJudicial recusa a operação antes de coagir o array para DpsData', function () {
    Http::fake(['*' => Http::response(['chaveAcesso' => 'CHAVE_ARRAY'], 201)]);

    $client = NfsenClient::for(makePfxContent(), 'secret', '9999999');

    // Array vazio: se a operação ainda coagisse para DpsData, a falha seria de
    // validação da DPS — e não a recusa da operação.
    expect(fn () => $client->emitirDecisaoJudicial([]))
        ->toThrow(NfseException::class, MENSAGEM_DECISAO_JUDICIAL);

    Http::assertNothingSent();
});
