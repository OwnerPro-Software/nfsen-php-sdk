<?php

covers(\Pulsar\NfseNacional\Operations\NfseSubstitutor::class);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Adapters\CertificateManager;
use Pulsar\NfseNacional\Adapters\NfseHttpClient;
use Pulsar\NfseNacional\Adapters\PrefeituraResolver;
use Pulsar\NfseNacional\Adapters\XmlSigner;
use Pulsar\NfseNacional\Enums\CodigoJustificativaSubstituicao;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Operations\NfseEmitter;
use Pulsar\NfseNacional\Operations\NfseSubstitutor;
use Pulsar\NfseNacional\Pipeline\NfseRequestPipeline;
use Pulsar\NfseNacional\Support\GzipCompressor;
use Pulsar\NfseNacional\Xml\Builders\SubstitutionBuilder;
use Pulsar\NfseNacional\Xml\DpsBuilder;

function makeNfseSubstitutor(): NfseSubstitutor
{
    $certManager = new CertificateManager(makeIcpBrPfxContent(), 'secret');
    $ambiente = NfseAmbiente::HOMOLOGACAO;
    $prefeituraResolver = new PrefeituraResolver(__DIR__.'/../../../storage/prefeituras.json');
    $httpClient = new NfseHttpClient($certManager->getCertificate(), 30, 10, true);
    $signer = new XmlSigner($certManager->getCertificate(), 'sha1');

    $pipeline = new NfseRequestPipeline(
        ambiente: $ambiente,
        prefeituraResolver: $prefeituraResolver,
        gzipCompressor: new GzipCompressor,
        signer: $signer,
        authorIdentity: $certManager,
        prefeitura: '9999999',
        httpClient: $httpClient,
    );

    $emitter = new NfseEmitter($pipeline, new DpsBuilder(makeXsdValidator()));

    return new NfseSubstitutor($emitter, $pipeline, new SubstitutionBuilder(makeXsdValidator()), $ambiente);
}

it('substituir injects subst into DPS and sends event XML without xMotivo', function () {
    $chave = '12345678901234567890123456789012345678901234567890';
    $chaveSub = '98765432109876543210987654321098765432109876543210';

    Http::fakeSequence()
        ->push(['chaveAcesso' => $chaveSub, 'nfseXmlGZipB64' => base64_encode(gzencode('<NFSe/>'))], 201)
        ->push(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201);

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
    expect($response->emissao->sucesso)->toBeTrue();
    expect($response->emissao->chave)->toBe($chaveSub);
    expect($response->evento)->not->toBeNull();
    expect($response->evento->sucesso)->toBeTrue();

    Http::assertSent(function (Request $req) {
        if (! isset($req['pedidoRegistroEventoXmlGZipB64'])) {
            return false;
        }

        $xml = gzdecode(base64_decode($req['pedidoRegistroEventoXmlGZipB64']));

        return ! str_contains($xml, '<nPedRegEvento>') &&
            ! str_contains($xml, '<xMotivo>');
    });
});
