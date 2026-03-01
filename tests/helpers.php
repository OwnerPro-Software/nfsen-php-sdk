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
    $infDps->tpamb = $overrides['tpamb'] ?? 2;
    $infDps->dhemi = $overrides['dhemi'] ?? '2026-02-27T10:00:00-03:00';
    $infDps->veraplic = $overrides['veraplic'] ?? '1.0';
    $infDps->serie = $overrides['serie'] ?? '1';
    $infDps->ndps = $overrides['ndps'] ?? 1;
    $infDps->dcompet = $overrides['dcompet'] ?? '2026-02-27';
    $infDps->tpemit = $overrides['tpemit'] ?? 1;
    $infDps->clocemi = $overrides['clocemi'] ?? '3501608';

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
    $prestador->cnpj = $overrides['cnpj'] ?? '12345678000195';
    $prestador->xnome = $overrides['xnome'] ?? 'Empresa Teste';

    $regTrib = new stdClass;
    $regTrib->opsimpnac = $overrides['opsimpnac'] ?? 1;
    $regTrib->regesptrib = $overrides['regesptrib'] ?? 0;
    $prestador->regtrib = $regTrib;

    return $prestador;
}

function makeServicoMinimo(): stdClass
{
    $servico = new stdClass;
    $servico->locprest = new stdClass;
    $servico->locprest->clocprestacao = '3501608';
    $servico->cserv = new stdClass;
    $servico->cserv->ctribnac = '010101';
    $servico->cserv->xdescserv = 'Serviço';

    return $servico;
}
