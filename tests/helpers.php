<?php

use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Certificates\CertificateManager;
use Pulsar\NfseNacional\DTOs\Dps\InfDPS\InfDPS;
use Pulsar\NfseNacional\DTOs\Dps\Prestador\Prestador;
use Pulsar\NfseNacional\DTOs\Dps\Servico\CodigoServico;
use Pulsar\NfseNacional\DTOs\Dps\Servico\Servico;
use Pulsar\NfseNacional\DTOs\Dps\Shared\RegTrib;
use Pulsar\NfseNacional\DTOs\Dps\Valores\Tributacao;
use Pulsar\NfseNacional\DTOs\Dps\Valores\TributacaoMunicipal;
use Pulsar\NfseNacional\DTOs\Dps\Valores\Valores;
use Pulsar\NfseNacional\DTOs\Dps\Valores\ValorServicoPrestado;
use Pulsar\NfseNacional\Enums\Dps\InfDPS\MotivoEmissaoTI;
use Pulsar\NfseNacional\Enums\Dps\InfDPS\TipoEmitente;
use Pulsar\NfseNacional\Enums\Dps\Prestador\OpSimpNac;
use Pulsar\NfseNacional\Enums\Dps\Prestador\RegEspTrib;
use Pulsar\NfseNacional\Enums\Dps\Valores\TipoRetISSQN;
use Pulsar\NfseNacional\Enums\Dps\Valores\TribISSQN;
use Pulsar\NfseNacional\Enums\NfseAmbiente;
use Pulsar\NfseNacional\Support\XsdValidator;

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
    ?TipoEmitente $tpEmit = null,
    ?string $cLocEmi = null,
    ?MotivoEmissaoTI $cMotivoEmisTI = null,
    ?string $chNFSeRej = null,
): InfDPS {
    return new InfDPS(
        tpAmb: $tpAmb ?? NfseAmbiente::HOMOLOGACAO,
        dhEmi: $dhEmi ?? '2026-02-27T10:00:00-03:00',
        verAplic: $verAplic ?? '1.0',
        serie: $serie ?? '1',
        nDPS: $nDPS ?? '1',
        dCompet: $dCompet ?? '2026-02-27',
        tpEmit: $tpEmit ?? TipoEmitente::Prestador,
        cLocEmi: $cLocEmi ?? '3501608',
        cMotivoEmisTI: $cMotivoEmisTI,
        chNFSeRej: $chNFSeRej,
    );
}

function makePrestadorCnpj(
    ?string $CNPJ = null,
    ?string $xNome = null,
    ?RegTrib $regTrib = null,
): Prestador {
    return new Prestador(
        CNPJ: $CNPJ ?? '12345678000195',
        regTrib: $regTrib ?? new RegTrib(
            opSimpNac: OpSimpNac::NaoOptante,
            regEspTrib: RegEspTrib::Nenhum,
        ),
        xNome: $xNome ?? 'Empresa Teste',
    );
}

function makeServicoMinimo(?string $cLocPrestacao = null): Servico
{
    return new Servico(
        cServ: new CodigoServico(
            cTribNac: '010101',
            xDescServ: 'Serviço',
            cNBS: '123456789',
        ),
        cLocPrestacao: $cLocPrestacao ?? '3501608',
    );
}

function makeValoresMinimo(?string $vServ = null): Valores
{
    return new Valores(
        vServPrest: new ValorServicoPrestado(vServ: $vServ ?? '100.00'),
        trib: new Tributacao(
            tribMun: new TributacaoMunicipal(
                tribISSQN: TribISSQN::Tributavel,
                tpRetISSQN: TipoRetISSQN::NaoRetido,
            ),
            indTotTrib: '0',
        ),
    );
}
