<?php

use Pulsar\NfseNacional\Xml\Builders\TomadorBuilder;

it('builds toma element with CNPJ', function () {
    $builder = new TomadorBuilder();
    $doc     = new DOMDocument('1.0', 'UTF-8');

    $toma = new stdClass();
    $toma->cnpj  = '98765432000111';
    $toma->xnome = 'Tomador Ltda';

    $element = $builder->build($doc, $toma);

    expect($doc->saveXML($element))->toContain('<CNPJ>98765432000111</CNPJ>');
    expect($doc->saveXML($element))->toContain('<xNome>Tomador Ltda</xNome>');
});
