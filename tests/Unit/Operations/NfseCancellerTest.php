<?php

use Illuminate\Support\Facades\Http;
use OwnerPro\Nfsen\Adapters\CertificateManager;
use OwnerPro\Nfsen\Adapters\NfseHttpClient;
use OwnerPro\Nfsen\Adapters\PrefeituraResolver;
use OwnerPro\Nfsen\Adapters\XmlSigner;
use OwnerPro\Nfsen\Enums\CodigoJustificativaCancelamento;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\Operations\NfseCanceller;
use OwnerPro\Nfsen\Pipeline\NfseRequestPipeline;
use OwnerPro\Nfsen\Support\GzipCompressor;
use OwnerPro\Nfsen\Xml\Builders\CancellationBuilder;

covers(NfseCanceller::class);

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
