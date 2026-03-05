<?php

covers(\Pulsar\NfseNacional\Operations\NfseCanceller::class);

use Illuminate\Support\Facades\Http;
use Pulsar\NfseNacional\Adapters\CertificateManager;
use Pulsar\NfseNacional\Adapters\NfseHttpClient;
use Pulsar\NfseNacional\Adapters\PrefeituraResolver;
use Pulsar\NfseNacional\Adapters\XmlSigner;
use Pulsar\NfseNacional\Enums\CodigoJustificativaCancelamento;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Operations\NfseCanceller;
use Pulsar\NfseNacional\Pipeline\NfseRequestPipeline;
use Pulsar\NfseNacional\Support\GzipCompressor;
use Pulsar\NfseNacional\Xml\Builders\CancellationBuilder;

function makeNfseCanceller(): NfseCanceller
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

    return new NfseCanceller($pipeline, new CancellationBuilder(makeXsdValidator()), $ambiente);
}

it('cancelar sends signed XML via pipeline', function () {
    Http::fake(['*' => Http::response(['eventoXmlGZipB64' => base64_encode(gzencode('<Evento/>'))], 201)]);

    $canceller = makeNfseCanceller();
    $response = $canceller->cancelar(
        '12345678901234567890123456789012345678901234567890',
        CodigoJustificativaCancelamento::ErroEmissao,
        'Erro na emissao da nota fiscal',
    );

    expect($response->sucesso)->toBeTrue();
});
