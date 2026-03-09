<?php

covers(\Pulsar\NfseNacional\Operations\NfseSubstitutor::class);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Events\NfseSubstituted;
use Pulsar\NfseNacional\Operations\NfseEmitter;
use Pulsar\NfseNacional\Operations\NfseSubstitutor;

function makeNfseSubstitutor(): NfseSubstitutor
{
    $certManager = new \Pulsar\NfseNacional\Adapters\CertificateManager(makeIcpBrPfxContent(), 'secret');
    $prefeituraResolver = new \Pulsar\NfseNacional\Adapters\PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $httpClient = new \Pulsar\NfseNacional\Adapters\NfseHttpClient($certManager->getCertificate(), 30, 10, true);
    $signer = new \Pulsar\NfseNacional\Adapters\XmlSigner($certManager->getCertificate(), 'sha1');

    $pipeline = new \Pulsar\NfseNacional\Pipeline\NfseRequestPipeline(
        ambiente: \Pulsar\NfseNacional\Enums\NfseAmbiente::HOMOLOGACAO,
        prefeituraResolver: $prefeituraResolver,
        gzipCompressor: new \Pulsar\NfseNacional\Support\GzipCompressor,
        signer: $signer,
        authorIdentity: $certManager,
        prefeitura: '9999999',
        httpClient: $httpClient,
    );

    $emitter = new NfseEmitter($pipeline, new \Pulsar\NfseNacional\Xml\DpsBuilder(makeXsdValidator()));

    return new NfseSubstitutor($emitter);
}

it('substituir injects subst into DPS and dispatches NfseSubstituted', function () {
    Event::fake();

    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fake(['*' => Http::response(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)]);

    $substitutor = makeNfseSubstitutor();
    $dps = new \Pulsar\NfseNacional\Dps\DTO\DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: makeServicoMinimo(),
        valores: makeValoresMinimo(),
    );

    $response = $substitutor->substituir(
        $chave,
        $dps,
        CodigoJustificativaSubstituicao::EnquadramentoSimplesNacional,
    );

    expect($response->sucesso)->toBeTrue();
    expect($response->chave)->toBe($chaveSub);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $req) use ($chave) {
        if (! isset($req['dpsXmlGZipB64'])) {
            return false;
        }

        $xml = gzdecode(base64_decode($req['dpsXmlGZipB64']));

        return str_contains($xml, '<chSubstda>'.$chave.'</chSubstda>') &&
            ! str_contains($xml, '<xMotivo>');
    });

    Event::assertDispatched(NfseSubstituted::class, fn (NfseSubstituted $e) => $e->chave === $chave && $e->chaveSubstituta === $chaveSub);
});

it('substituir does not dispatch NfseSubstituted on failure', function () {
    Event::fake();

    $chave = '12345678901234567890123456789012345678901234567890';

    Http::fake(['*' => Http::response(['erros' => [['descricao' => 'DPS inválido', 'codigo' => 'E001']]], 200)]);

    $substitutor = makeNfseSubstitutor();
    $dps = new \Pulsar\NfseNacional\Dps\DTO\DpsData(
        infDPS: makeInfDps(),
        prest: makePrestadorCnpj(),
        serv: makeServicoMinimo(),
        valores: makeValoresMinimo(),
    );

    $response = $substitutor->substituir(
        $chave,
        $dps,
        CodigoJustificativaSubstituicao::Outros,
        'Motivo para substituicao da nota fiscal',
    );

    expect($response->sucesso)->toBeFalse();

    Event::assertNotDispatched(NfseSubstituted::class);
});
