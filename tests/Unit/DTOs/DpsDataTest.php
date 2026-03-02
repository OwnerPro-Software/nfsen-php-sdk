<?php

use Pulsar\NfseNacional\DTOs\Dps\DpsData;
use Pulsar\NfseNacional\Xml\DpsBuilder;

it('exposes all five groups as readonly properties', function () {
    $infDps = makeInfDps();
    $prestador = makePrestadorCnpj();
    $servico = makeServicoMinimo();
    $valores = makeValoresMinimo();

    $data = new DpsData(
        infDPS: $infDps,
        subst: null,
        prest: $prestador,
        toma: null,
        interm: null,
        serv: $servico,
        valores: $valores,
    );

    expect($data)
        ->infDPS->toBe($infDps)
        ->prest->toBe($prestador)
        ->toma->toBeNull()
        ->subst->toBeNull()
        ->interm->toBeNull()
        ->serv->toBe($servico)
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
