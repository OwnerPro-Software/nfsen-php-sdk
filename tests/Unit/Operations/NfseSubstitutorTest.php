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
use Pulsar\NfseNacional\Operations\NfseSubstitutor;
use Pulsar\NfseNacional\Pipeline\NfseRequestPipeline;
use Pulsar\NfseNacional\Support\GzipCompressor;
use Pulsar\NfseNacional\Xml\Builders\SubstitutionBuilder;

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

    return new NfseSubstitutor($pipeline, new SubstitutionBuilder(makeXsdValidator()), $ambiente);
}

it('substituir sends signed XML via pipeline without xMotivo', function () {
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201)]);

    $substitutor = makeNfseSubstitutor();
    $response = $substitutor->substituir(
        '12345678901234567890123456789012345678901234567890',
        '98765432109876543210987654321098765432109876543210',
        CodigoJustificativaSubstituicao::EnquadramentoSimplesNacional,
    );

    expect($response->sucesso)->toBeTrue();

    Http::assertSent(function (Request $req) {
        $xml = gzdecode(base64_decode($req['pedidoRegistroEventoXmlGZipB64']));

        return ! str_contains($xml, '<nPedRegEvento>') &&
            ! str_contains($xml, '<xMotivo>');
    });
});
