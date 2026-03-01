<?php

use NFePHP\Common\Certificate;
use Pulsar\NfseNacional\Certificates\CertificateManager;
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

/**
 * @param  array<string, mixed>  $overrides
 */
function makeInfDps(array $overrides = []): stdClass
{
    $infDps = new stdClass;
    $infDps->tpAmb = $overrides['tpAmb'] ?? 2;
    $infDps->dhEmi = $overrides['dhEmi'] ?? '2026-02-27T10:00:00-03:00';
    $infDps->verAplic = $overrides['verAplic'] ?? '1.0';
    $infDps->serie = $overrides['serie'] ?? '1';
    $infDps->nDPS = $overrides['nDPS'] ?? 1;
    $infDps->dCompet = $overrides['dCompet'] ?? '2026-02-27';
    $infDps->tpEmit = $overrides['tpEmit'] ?? 1;
    $infDps->cLocEmi = $overrides['cLocEmi'] ?? '3501608';

    foreach ($overrides as $key => $value) {
        if (! property_exists($infDps, $key)) {
            $infDps->{$key} = $value;
        }
    }

    return $infDps;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function makePrestadorCnpj(array $overrides = []): stdClass
{
    $prestador = new stdClass;
    $prestador->CNPJ = $overrides['CNPJ'] ?? '12345678000195';
    $prestador->xNome = $overrides['xNome'] ?? 'Empresa Teste';

    $regTrib = new stdClass;
    $regTrib->opSimpNac = $overrides['opSimpNac'] ?? 1;
    $regTrib->regEspTrib = $overrides['regEspTrib'] ?? 0;
    $prestador->regTrib = $regTrib;

    return $prestador;
}

function makeServicoMinimo(): stdClass
{
    $servico = new stdClass;
    $servico->locPrest = new stdClass;
    $servico->locPrest->cLocPrestacao = '3501608';
    $servico->cServ = new stdClass;
    $servico->cServ->cTribNac = '010101';
    $servico->cServ->xDescServ = 'Serviço';
    $servico->cServ->cNBS = '123456789';

    return $servico;
}
