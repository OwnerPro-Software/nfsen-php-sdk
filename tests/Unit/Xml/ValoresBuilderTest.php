<?php

use Pulsar\NfseNacional\Xml\Builders\ValoresBuilder;

it('builds valores element with vServPrest', function () {
    $builder = new ValoresBuilder();
    $doc     = new DOMDocument('1.0', 'UTF-8');

    $valores = new stdClass();
    $valores->vservprest = new stdClass();
    $valores->vservprest->vtrib  = '100.00';
    $valores->vservprest->vdeduct = null;

    $element = $builder->build($doc, $valores);
    $xml     = $doc->saveXML($element);

    expect($xml)->toContain('<valores>');
    expect($xml)->toContain('<vServPrest>');
    expect($xml)->toContain('<vTrib>100.00</vTrib>');
});
