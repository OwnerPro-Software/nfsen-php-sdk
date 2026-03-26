<?php

use NFePHP\Common\Certificate;
use OwnerPro\Nfsen\Adapters\CertificateManager;
use OwnerPro\Nfsen\Adapters\NfseHttpClient;
use OwnerPro\Nfsen\Adapters\PrefeituraResolver;
use OwnerPro\Nfsen\Adapters\XmlSigner;
use OwnerPro\Nfsen\Dps\DTO\InfDPS\InfDPS;
use OwnerPro\Nfsen\Dps\DTO\Prest\Prest;
use OwnerPro\Nfsen\Dps\DTO\Serv\CServ;
use OwnerPro\Nfsen\Dps\DTO\Serv\Serv;
use OwnerPro\Nfsen\Dps\DTO\Shared\RegTrib;
use OwnerPro\Nfsen\Dps\DTO\Valores\Trib;
use OwnerPro\Nfsen\Dps\DTO\Valores\TribMun;
use OwnerPro\Nfsen\Dps\DTO\Valores\Valores;
use OwnerPro\Nfsen\Dps\DTO\Valores\VServPrest;
use OwnerPro\Nfsen\Dps\Enums\InfDPS\CMotivoEmisTI;
use OwnerPro\Nfsen\Dps\Enums\InfDPS\TpEmit;
use OwnerPro\Nfsen\Dps\Enums\Prest\OpSimpNac;
use OwnerPro\Nfsen\Dps\Enums\Prest\RegEspTrib;
use OwnerPro\Nfsen\Dps\Enums\Valores\TpRetISSQN;
use OwnerPro\Nfsen\Dps\Enums\Valores\TribISSQN;
use OwnerPro\Nfsen\Enums\NfseAmbiente;
use OwnerPro\Nfsen\NfsenClient;
use OwnerPro\Nfsen\Operations\NfseCanceller;
use OwnerPro\Nfsen\Operations\NfseConsulter;
use OwnerPro\Nfsen\Operations\NfseEmitter;
use OwnerPro\Nfsen\Operations\NfseSubstitutor;
use OwnerPro\Nfsen\Pipeline\NfseRequestPipeline;
use OwnerPro\Nfsen\Pipeline\NfseResponsePipeline;
use OwnerPro\Nfsen\Support\GzipCompressor;
use OwnerPro\Nfsen\Support\XsdValidator;
use OwnerPro\Nfsen\Xml\Builders\CancellationBuilder;
use OwnerPro\Nfsen\Xml\DpsBuilder;

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

function makeNfsenClient(
    ?GzipCompressor $gzipCompressor = null,
    ?string $pfxContent = null,
    string $prefeitura = '9999999',
): NfsenClient {
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
        validateIdentity: false,
    );

    $queryExecutor = new NfseResponsePipeline($httpClient);
    $seFinUrl = $prefeituraResolver->resolveSeFinUrl($prefeitura, $ambiente);
    $adnUrl = $prefeituraResolver->resolveAdnUrl($prefeitura, $ambiente);

    $emitter = new NfseEmitter($pipeline, new DpsBuilder($xsdValidator));

    return new NfsenClient(
        emitter: $emitter,
        canceller: new NfseCanceller($pipeline, new CancellationBuilder($xsdValidator), $ambiente),
        substitutor: new NfseSubstitutor($emitter),
        consulter: new NfseConsulter($queryExecutor, $seFinUrl, $adnUrl, $prefeituraResolver, $prefeitura),
    );
}
