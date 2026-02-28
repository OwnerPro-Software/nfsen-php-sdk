<?php

use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Xml\DpsBuilder;

it('produces xml that validates against DPS_v1.01.xsd', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->buildAndValidate($data);

    // Se chegou aqui sem exception, o XML é válido
    expect($xml)->toContain('<DPS ');
})->with('dpsData');

it('DPS_v1.01.xsd scheme file exists', function () {
    $path = __DIR__ . '/../../../storage/schemes/DPS_v1.01.xsd';
    expect(file_exists($path))->toBeTrue();
    expect(filesize($path))->toBeGreaterThan(0);
});

it('build() does not validate XSD (fast path)', function (DpsData $data) {
    $builder = new DpsBuilder(__DIR__ . '/../../../storage/schemes');
    $xml     = $builder->build($data);

    // build() retorna XML sem validar — não lança exceção mesmo se inválido
    expect($xml)->toBeString();
})->with('dpsData');
