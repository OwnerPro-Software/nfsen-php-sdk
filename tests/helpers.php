<?php

use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Adapters\CertificateManager;
use Pulsar\NfseNacional\Adapters\NfseHttpClient;
use Pulsar\NfseNacional\Adapters\PrefeituraResolver;
use Pulsar\NfseNacional\Adapters\XmlSigner;
use Pulsar\NfseNacional\Dps\DTO\InfDPS\InfDPS;
use Pulsar\NfseNacional\Dps\DTO\Prest\Prest;
use Pulsar\NfseNacional\Dps\DTO\Serv\CServ;
use Pulsar\NfseNacional\Dps\DTO\Serv\Serv;
use Pulsar\NfseNacional\Dps\DTO\Shared\RegTrib;
use Pulsar\NfseNacional\Dps\DTO\Valores\Trib;
use Pulsar\NfseNacional\Dps\DTO\Valores\TribMun;
use Pulsar\NfseNacional\Dps\DTO\Valores\Valores;
use Pulsar\NfseNacional\Dps\DTO\Valores\VServPrest;
use Pulsar\NfseNacional\Dps\Enums\InfDPS\CMotivoEmisTI;
use Pulsar\NfseNacional\Dps\Enums\InfDPS\TpEmit;
use Pulsar\NfseNacional\Dps\Enums\Prest\OpSimpNac;
use Pulsar\NfseNacional\Dps\Enums\Prest\RegEspTrib;
use Pulsar\NfseNacional\Dps\Enums\Valores\TpRetISSQN;
use Pulsar\NfseNacional\Dps\Enums\Valores\TribISSQN;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\NfseClient;
use Pulsar\NfseNacional\Operations\NfseCanceller;
use Pulsar\NfseNacional\Operations\NfseConsulter;
use Pulsar\NfseNacional\Operations\NfseEmitter;
use Pulsar\NfseNacional\Operations\NfseSubstitutor;
use Pulsar\NfseNacional\Pipeline\NfseRequestPipeline;
use Pulsar\NfseNacional\Pipeline\NfseResponsePipeline;
use Pulsar\NfseNacional\Support\GzipCompressor;
use Pulsar\NfseNacional\Support\XsdValidator;
use Pulsar\NfseNacional\Xml\Builders\CancellationBuilder;
use Pulsar\NfseNacional\Xml\Builders\SubstitutionBuilder;
use Pulsar\NfseNacional\Xml\DpsBuilder;

function makePfxContent(): string
{
    return file_get_contents(__DIR__.'/fixtures/certs/fake.pfx');
}

function makeIcpBrPfxContent(): string
{
    return file_get_contents(__DIR__.'/fixtures/certs/fake-icpbr.pfx');
}

function makeXsdValidator(): XsdValidator
{
    return new XsdValidator(__DIR__.'/../storage/schemes');
}

function makeTestCertificate(): Certificate
{
    return (new CertificateManager(makePfxContent(), 'secret'))->getCertificate();
}

function makeInfDps(
    ?NfseAmbiente $tpAmb = null,
    ?string $dhEmi = null,
    ?string $verAplic = null,
    ?string $serie = null,
    ?string $nDPS = null,
    ?string $dCompet = null,
    ?TpEmit $tpEmit = null,
    ?string $cLocEmi = null,
    ?CMotivoEmisTI $cMotivoEmisTI = null,
    ?string $chNFSeRej = null,
): InfDPS {
    return new InfDPS(
        tpAmb: $tpAmb ?? NfseAmbiente::HOMOLOGACAO,
        dhEmi: $dhEmi ?? '2026-02-27T10:00:00-03:00',
        verAplic: $verAplic ?? '1.0',
        serie: $serie ?? '1',
        nDPS: $nDPS ?? '1',
        dCompet: $dCompet ?? '2026-02-27',
        tpEmit: $tpEmit ?? TpEmit::Prestador,
        cLocEmi: $cLocEmi ?? '3501608',
        cMotivoEmisTI: $cMotivoEmisTI,
        chNFSeRej: $chNFSeRej,
    );
}

function makePrestadorCnpj(
    ?string $CNPJ = null,
    ?string $xNome = null,
    ?RegTrib $regTrib = null,
): Prest {
    return new Prest(
        CNPJ: $CNPJ ?? '12345678000195',
        regTrib: $regTrib ?? new RegTrib(
            opSimpNac: OpSimpNac::NaoOptante,
            regEspTrib: RegEspTrib::Nenhum,
        ),
        xNome: $xNome ?? 'Empresa Teste',
    );
}

function makeServicoMinimo(?string $cLocPrestacao = null): Serv
{
    return new Serv(
        cServ: new CServ(
            cTribNac: '010101',
            xDescServ: 'Serviço',
            cNBS: '123456789',
        ),
        cLocPrestacao: $cLocPrestacao ?? '3501608',
    );
}

function makeChaveAcesso(): string
{
    return '12345678901234567890123456789012345678901234567890';
}

function makeValoresMinimo(?string $vServ = null): Valores
{
    return new Valores(
        vServPrest: new VServPrest(vServ: $vServ ?? '100.00'),
        trib: new Trib(
            tribMun: new TribMun(
                tribISSQN: TribISSQN::Tributavel,
                tpRetISSQN: TpRetISSQN::NaoRetido,
            ),
            indTotTrib: '0',
        ),
    );
}

function makeNfseClient(
    ?GzipCompressor $gzipCompressor = null,
    ?string $pfxContent = null,
    string $prefeitura = '9999999',
): NfseClient {
    $pfxContent ??= makePfxContent();
    $certManager = new CertificateManager($pfxContent, 'secret');
    $ambiente = NfseAmbiente::HOMOLOGACAO;
    $prefeituraResolver = new PrefeituraResolver(__DIR__.'/../storage/prefeituras.json');
    $xsdValidator = makeXsdValidator();
    $httpClient = new NfseHttpClient($certManager->getCertificate(), 30, 10, true);
    $signer = new XmlSigner($certManager->getCertificate(), 'sha1');

    $pipeline = new NfseRequestPipeline(
        ambiente: $ambiente,
        prefeituraResolver: $prefeituraResolver,
        gzipCompressor: $gzipCompressor ?? new GzipCompressor,
        signer: $signer,
        authorIdentity: $certManager,
        prefeitura: $prefeitura,
        httpClient: $httpClient,
    );

    $queryExecutor = new NfseResponsePipeline($httpClient);
    $seFinUrl = $prefeituraResolver->resolveSeFinUrl($prefeitura, $ambiente);
    $adnUrl = $prefeituraResolver->resolveAdnUrl($prefeitura, $ambiente);

    $emitter = new NfseEmitter($pipeline, new DpsBuilder($xsdValidator));

    return new NfseClient(
        emitter: $emitter,
        canceller: new NfseCanceller($pipeline, new CancellationBuilder($xsdValidator), $ambiente),
        substitutor: new NfseSubstitutor($emitter, $pipeline, new SubstitutionBuilder($xsdValidator), $ambiente),
        consulter: new NfseConsulter($queryExecutor, $seFinUrl, $adnUrl, $prefeituraResolver, $prefeitura),
    );
}
