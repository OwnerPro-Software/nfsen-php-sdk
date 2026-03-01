<?php

use Pulsar\NfseNacional\DTOs\DpsData;
use Pulsar\NfseNacional\Xml\DpsBuilder;

it('exposes all five groups as readonly properties', function () {
    $infDps = new stdClass;
    $prestador = new stdClass;
    $tomador = new stdClass;
    $servico = new stdClass;
    $valores = new stdClass;

    $data = new DpsData($infDps, $prestador, $tomador, $servico, $valores);

    expect($data)
        ->infDps->toBe($infDps)
        ->prestador->toBe($prestador)
        ->tomador->toBe($tomador)
        ->servico->toBe($servico)
        ->valores->toBe($valores);
});

it('produces valid XML when passed to DpsBuilder', function (DpsData $data) {
    $builder = new DpsBuilder(makeXsdValidator());
    $xml = $builder->build($data);

    $doc = new DOMDocument;
    $doc->loadXML($xml);

    expect($doc->getElementsByTagName('DPS')->length)->toBe(1);
    expect($doc->getElementsByTagName('infDPS')->length)->toBe(1);
    expect($doc->getElementsByTagName('tpAmb')->item(0)->textContent)->toBe('2');
    expect($doc->getElementsByTagName('serie')->item(0)->textContent)->toBe('1');
})->with('dpsData');
