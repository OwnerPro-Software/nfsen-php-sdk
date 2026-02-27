<?php

use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Xml\DpsBuilder;

it('builds xml with DPS root element', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('<DPS ');
    expect($xml)->toContain('versao=');
    expect($xml)->toContain('xmlns="http://www.sped.fazenda.gov.br/nfse"');
})->with('dpsData');

it('builds xml with infDPS Id', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('<infDPS Id="DPS');
})->with('dpsData');

it('includes tpAmb in infDPS', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    expect($xml)->toContain('<tpAmb>2</tpAmb>');
})->with('dpsData');
